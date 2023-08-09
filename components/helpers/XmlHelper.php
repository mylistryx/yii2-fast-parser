<?php

namespace app\components\helpers;

use SimpleXMLElement;

class XmlHelper
{
    public static function xmlGet(&$from, $key, bool $cut = false): mixed
    {
        if ($key && is_array($key)) {

            $sub = array_shift($key);
            $temp = static::xmlGet($from, $sub);
            if (count($key) == 0) {
                return $temp;
            }
            return static::xmlGet($temp, $key, false);
        }
        if (isset($from[$key])) {
            $data = $from[$key];
            if ($cut) {
                unset($from[$key]);
            }
            return $data;
        }

        if (str_starts_with($key, '@')) {
            return null;
        }

        return [];
    }

    public static function xmlToArray(SimpleXMLElement $xml, array $options = []): array
    {
        $defaults = [
            'namespaceRecursive' => false,  //setting to true will get xml doc namespaces recursively
            'removeNamespace' => false,     //set to true if you want to remove the namespace from resulting keys (recommend setting namespaceSeparator = '' when this is set to true)
            'namespaceSeparator' => ':',    //you may want this to be something other than a colon
            'attributePrefix' => '@',       //to distinguish between attributes and nodes with the same name
            'alwaysArray' => [],       //array of xml tag names which should always become arrays
            'autoArray' => true,            //only create arrays for tags which appear more than once
            'textContent' => '$',           //key used for the text content of elements
            'autoText' => true,             //skip textContent key if node has no attributes or child nodes
            'keySearch' => false,           //optional search and replace on tag and attribute names
            'keyReplace' => false,           //replace values for above search values (as passed to str_replace())
        ];
        $options = array_merge($defaults, $options);
        $namespaces = $xml->getDocNamespaces($options['namespaceRecursive']);
        $namespaces[''] = null; //add base (empty) namespace

        //get attributes from all namespaces
        $attributesArray = [];
        foreach ($namespaces as $prefix => $namespace) {
            if ($options['removeNamespace']) {
                $prefix = '';
            }
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                //replace characters in attribute name
                if ($options['keySearch']) {
                    $attributeName =
                        str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
                }
                $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
                $attributesArray[$attributeKey] = (string)$attribute;
            }
        }

        //get child nodes from all namespaces
        $tagsArray = [];
        foreach ($namespaces as $prefix => $namespace) {
            if ($options['removeNamespace']) {
                $prefix = '';
            }

            foreach ($xml->children($namespace) as $childXml) {
                //recurse into child nodes
                $childArray = static::xmlToArray($childXml, $options);
                $childTagName = key($childArray);
                $childProperties = current($childArray);

                //replace characters in tag name
                if ($options['keySearch']) {
                    $childTagName =
                        str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
                }

                //add namespace prefix, if any
                if ($prefix) {
                    $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;
                }

                if (!isset($tagsArray[$childTagName])) {
                    //only entry with this key
                    //test if tags of this type should always be arrays, no matter the element count
                    $tagsArray[$childTagName] =
                        in_array($childTagName, $options['alwaysArray'], true) || !$options['autoArray']
                            ? [$childProperties] : $childProperties;
                } elseif (
                    is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                    === range(0, count($tagsArray[$childTagName]) - 1)
                ) {
                    //key already exists and is integer indexed array
                    $tagsArray[$childTagName][] = $childProperties;
                } else {
                    //key exists so convert to integer indexed array with previous value in position 0
                    $tagsArray[$childTagName] = [$tagsArray[$childTagName], $childProperties];
                }
            }
        }

        //get text content of node
        $textContentArray = [];
        $plainText = trim((string)$xml);
        if ($plainText !== '') {
            $textContentArray[$options['textContent']] = $plainText;
        }

        //stick it all together
        $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

        //return node as array
        return [
            $xml->getName() => $propertiesArray,
        ];
    }
}