<?php

namespace app\components\parsers;

use app\components\helpers\XmlHelper;
use app\models\od\Opf;
use app\models\od\Source;
use app\models\od\ul\Ul;
use app\models\od\ul\UlAddress;
use app\models\od\ul\UlAppeal;
use app\models\od\ul\UlCapital;
use app\models\od\ul\UlClose;
use app\models\od\ul\UlFounder;
use app\models\od\ul\UlFssReg;
use app\models\od\ul\UlLicence;
use app\models\od\ul\UlManagingOrganization;
use app\models\od\ul\UlName;
use app\models\od\ul\UlNext;
use app\models\od\ul\UlOkved;
use app\models\od\ul\UlPart;
use app\models\od\ul\UlPfReg;
use app\models\od\ul\UlPositionOfIndividual;
use app\models\od\ul\UlPrevious;
use app\models\od\ul\UlRecord;
use app\models\od\ul\UlRegistryKeeper;
use app\models\od\ul\UlRegOrg;
use app\models\od\ul\UlReorganization;
use app\models\od\ul\UlStatus;
use app\models\od\ul\UlSubdivision;
use app\models\od\ul\UlTaxReg;

class EgrUlParser extends XmlParser
{
    public function __construct(
        private readonly Source $source,
    )
    {
    }

    protected function process(array $data = []): void
    {
        $ogrn = XmlHelper::xmlGet($data, '@ОГРН', true);
        $inn = XmlHelper::xmlGet($data, '@ИНН', true);
        $kpp = XmlHelper::xmlGet($data, '@КПП', true);
        $date = XmlHelper::xmlGet($data, '@ДатаОГРН', true);
        $outLoadDate = XmlHelper::xmlGet($data, '@ДатаВып', true);

        if ($ogrn && $inn && $kpp) {
            echo "\nProcessing OGRN: $ogrn INN: $inn KPP: $kpp ";
            $model = Ul::factory($ogrn, $inn, $kpp);
            if (!$model->isNewRecord) {
                echo " [SKIP] ";
                return;
            }
            $model->source_id = $this->source->id;
            $model->date = $date;
            $model->date_ogrn = $outLoadDate;
            if ($email = XmlHelper::xmlGet($data, 'СвАдрЭлПочты', true)) {
                $model->email = $email['@E-mail'];
            }

            $model->opf_code = XmlHelper::xmlGet($data, '@КодОПФ', true);
            $model->opf_type = XmlHelper::xmlGet($data, '@СпрОПФ', true);
            $model->opf_name = XmlHelper::xmlGet($data, '@ПолнНаимОПФ', true);


            $model->tryTosave($data);

            $dataModel = new FlData([
                'fl_id' => $model->id,
                'data' => $data
            ]);

            $dataModel->tryToSave();

            echo "[ DONE ]";
        } else {
            /** Если что-то пошло не так */
            self::showAndDie($data);
        }

        //***********************************************************************//

        if ($temp = XmlHelper::xmlGet($data, 'СвНаимЮЛ', true)) {
            UlName::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвУчетНО', true)) {
            UlTaxReg::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвРегПФ', true)) {
            UlPfReg::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвРегФСС', true)) {
            UlFssReg::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвЛицензия', true)) {
            UlLicence::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвОКВЭД', true)) {
            UlOkved::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвАдресЮЛ', true)) {
            UlAddress::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвПредш', true)) {
            UlPrevious::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвКФХПредш', true)) {
            UlPrevious::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвПреем', true)) {
            UlNext::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвОбрЮЛ', true)) {
            UlAppeal::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвРегОрг', true)) {
            UlRegOrg::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвУстКап', true)) {
            UlCapital::process($temp, $this->source, $model);
        }

        /** CHECK */

        if ($temp = XmlHelper::xmlGet($data, 'СвЗапЕГРЮЛ', true)) {
            UlRecord::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвУпрОрг', true)) {
            UlManagingOrganization::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвУчредит', true)) {
            UlFounder::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвДоляООО', true)) {
            UlPart::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвПодразд', true)) {
            UlSubdivision::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвДержРеестрАО', true)) {
            UlRegistryKeeper::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвПрекрЮЛ', true)) {
            UlClose::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвСтатус', true)) {
            UlStatus::process($temp, $this->source, $model);
        }

        if ($temp = XmlHelper::xmlGet($data, 'СвРеорг', true)) {
            UlReorganization::process($temp, $this->source, $model);
        }

        /** ToFIX */

        if ($temp = XmlHelper::xmlGet($data, 'СведДолжнФЛ', true)) {
            if (XmlHelper::xmlGet($temp, ['СвФЛ', '@ОгрДосСв'])) {
                /** SKIP, как обработать? Данные скрыты! */
            } elseif (XmlHelper::xmlGet($temp, 'СвФЛ')) {
                UlPositionOfIndividual::process($temp, $this->source, $model);
            } else {
                foreach ($temp as $one) {
                    UlPositionOfIndividual::process($one, $this->source, $model);
                }
            }
        }


        if (count($data)) {
            self::showAndDie($data);
        }

        echo "[ ALL DONE ]";
    }
}