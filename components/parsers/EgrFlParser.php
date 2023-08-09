<?php

namespace app\components\parsers;

use app\components\helpers\XmlHelper;
use app\models\od\fl\Fl;

use app\models\od\Source;

class EgrFlParser extends XmlParser
{
    public function __construct(
        private readonly Source $source,
    )
    {
    }

    public function process(array $data): void
    {
        var_dump($data);
        die();


        /** С этими данными будем шагать весь цикл заполнения */
        $outLoadDate = XmlHelper::xmlGet($data, '@ДатаВып', true);
        $ogrnIp = XmlHelper::xmlGet($data, '@ОГРНИП', true);

        if (empty ($ogrnIp) or empty($outLoadDate)) {
            var_dump($data);
            die();
            return;
        }

        /**
         * Есть записи с ОГННИП, но без ИНН. Часто это доп.сведения, или сведения о ликвидации.
         * Проверим, что наши данные новее тех, что есть, и есть ли вообще
         */

        $model = Fl::factory($ogrnIp);

        /**
         * Обновим данные, source_id будет указывать на последний актуальный источник.
         * Могут поменяться:
         * - регион
         * - ФИО
         * - ... ?
         */
        if (!$model->isNewRecord) {
            if ($model->date >= $outLoadDate) {
                /** SKIP! */
            } else {
                $model->date = $outLoadDate;
                $model->source_id = $this->source->id;
                $this->tryToSave($model, $data);
            }
        }


        $flData = XmlHelper::xmlGet($data, 'СвФЛ', true);
        $flName = XmlHelper::xmlGet($flData, 'ФИОРус', true);
        /** UNSET */
        XmlHelper::xmlGet($data, '@НаимВидИП', true);

        $model->inn = XmlHelper::xmlGet($data, '@ИННФЛ', true);
        $model->date_ogrnip = XmlHelper::xmlGet($data, '@ДатаОГРНИП', true);
        $model->type_code = XmlHelper::xmlGet($data, '@КодВидИП', true);
        $model->gender = XmlHelper::xmlGet($flData, '@Пол', true);
        $model->fio = trim(implode(' ', $flName));
        if ($email = XmlHelper::xmlGet($data, 'СвАдрЭлПочты', true)) {
            $model->email = $email['@E-mail'];
        }

        /**
         * Если есть ОГРНИП и не найдена уже существующая запись, то добавим её
         */
        if ($model->isNewRecord) {
            $model->date = $outLoadDate;
            $model->source_id = $this->source->id;
            $this->tryToSave($model);
        }


    }
}