<?php

namespace Raml\Utility;

use Inflect\Inflect;

class TraitParserHelper
{
    /**
     * @return array
     */
    public static function applyVariables(array $values, array $traitDefinition)
    {
        $newTrait = [];

        foreach ($traitDefinition as $key => &$value) {
            $newKey = static::applyFunctions($key, $values);

            $value = \is_array($value) ? static::applyVariables($values, $value) : static::applyFunctions($value, $values);
            $newTrait[$newKey] = $value;
        }

        return $newTrait;
    }

    /**
     * @param string $trait
     * @return string
     */
    private static function applyFunctions($trait, array $values)
    {
        if (empty($trait)) {
            return ;
        }

        $variables = \implode('|', \array_keys($values));

        return \preg_replace_callback(
            '/<<(' . $variables . ')' .
            '(' .
            '[\s]*\|[\s]*!' .
            '(' .
            'singularize|pluralize|uppercase|lowercase|lowercamelcase|uppercamelcase|lowerunderscorecase|upperunderscorecase|lowerhyphencase|upperhyphencase' .
            ')' .
            ')?>>/',
            static function ($matches) use ($values) {
                $transformer = $matches[3] ?? '';
                switch ($transformer) {
                    case 'singularize':
                        return Inflect::singularize($values[$matches[1]]);

                        break;
                    case 'pluralize':
                        return Inflect::pluralize($values[$matches[1]]);

                        break;
                    case 'uppercase':
                        return \mb_strtoupper($values[$matches[1]]);

                        break;
                    case 'lowercase':
                        return \mb_strtolower($values[$matches[1]]);

                        break;
                    case 'lowercamelcase':
                        return StringTransformer::convertString(
                            $values[$matches[1]],
                            StringTransformer::LOWER_CAMEL_CASE
                        );

                        break;
                    case 'uppercamelcase':
                        return StringTransformer::convertString(
                            $values[$matches[1]],
                            StringTransformer::UPPER_CAMEL_CASE
                        );

                        break;
                    case 'lowerunderscorecase':
                        return StringTransformer::convertString(
                            $values[$matches[1]],
                            StringTransformer::LOWER_UNDERSCORE_CASE
                        );

                        break;
                    case 'upperunderscorecase':
                        return StringTransformer::convertString(
                            $values[$matches[1]],
                            StringTransformer::UPPER_UNDERSCORE_CASE
                        );

                        break;
                    case 'lowerhyphencase':
                        return StringTransformer::convertString(
                            $values[$matches[1]],
                            StringTransformer::LOWER_HYPHEN_CASE
                        );

                        break;
                    case 'upperhyphencase':
                        return StringTransformer::convertString(
                            $values[$matches[1]],
                            StringTransformer::UPPER_HYPHEN_CASE
                        );

                        break;
                    default:
                        return $values[$matches[1]];
                }
            },
            $trait
        );
    }
}
