<?php

namespace app\components\parsers;

use app\components\helpers\XmlHelper;
use app\models\od\Source;

abstract class XmlParser
{
    public static function factory(Source $source, string $path): void
    {
        $memory = memory_get_usage(true);
        $xml = simplexml_load_file($path);
        foreach ($xml as $one) {
            $array = XmlHelper::xmlToArray($one);
            $keys = [];
            unset($one);
            if (isset($array['СвИП'])) {
                $parser = new EgrFlParser($source);
                $parser->process($array["СвИП"]);

            } elseif (isset($array["СвЮЛ"])) {
                $parser = new EgrUlParser($source);
                $parser->process($array['СвЮЛ']);
            }

            unset($keys);
            unset($parser);
            unset($array);
            gc_collect_cycles();
        }
        echo " [ MU: " . round((memory_get_usage(true) - $memory) / 1024) . ' Kb ]';
        unset($xml);
    }

    protected static function showAndDie(mixed $data): void
    {
        echo "\nЧет пошло не так!\n";
        var_dump($data);
        die();
    }

    protected function tryToSave($record, array $data = []): void
    {
        if (!$record->save()) {
            $name = static::class;
            echo "\nОшибка при сохранении модели $name \n";
            var_dump($data);
            var_dump($record->errors);
            die();
        }
    }

    abstract protected function process(array $data): void;
}