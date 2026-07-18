<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp\Rize\UriTemplate\Operator;

use DeliciousBrains\WP_Offload_Media\Gcp\Rize\UriTemplate\Node;
use DeliciousBrains\WP_Offload_Media\Gcp\Rize\UriTemplate\Parser;
/**
 * | 1   |    {?list}    ?list=red,green,blue                 | {name}=(?:\w+(?:,\w+?)*)*
 * | 2   |    {?list*}   ?list=red&list=green&list=blue       | {name}+=(?:{$value}+(?:{sep}{name}+={$value}*))*
 * | 3   |    {?keys}    ?keys=semi,%3B,dot,.,comma,%2C       | (same as 1)
 * | 4   |    {?keys*}   ?semi=%3B&dot=.&comma=%2C            | (same as 2)
 * | 5   |    {?list*}   ?list[]=red&list[]=green&list[]=blue | {name[]}+=(?:{$value}+(?:{sep}{name[]}+={$value}*))*.
 */
class Named extends Abstraction
{
    public function toRegex(Parser $parser, Node\Variable $var) : string
    {
        $name = $var->name;
        $value = $this->getRegex();
        $options = $var->options;
        if ($options['modifier']) {
            switch ($options['modifier']) {
                case '*':
                    // 2 | 4
                    $regex = "{$name}+=(?:{$value}+(?:{$this->sep}{$name}+={$value}*)*)" . "|{$value}+=(?:{$value}+(?:{$this->sep}{$value}+={$value}*)*)";
                    break;
                case ':':
                    $regex = "{$value}\\{0,{$options['value']}\\}";
                    break;
                case '%':
                    // 5
                    $name .= '+(?:%5B|\\[)[^=]*=';
                    $regex = "{$name}(?:{$value}+(?:{$this->sep}{$name}{$value}*)*)";
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown modifier `{$options['modifier']}`");
            }
        } else {
            // 1, 3
            $regex = "{$name}=(?:{$value}+(?:,{$value}+)*)*";
        }
        return '(?:&)?' . $regex;
    }
    public function expandString(Parser $parser, Node\Variable $var, $val) : string
    {
        $val = (string) $val;
        $options = $var->options;
        $result = $this->encode($parser, $var, $var->name);
        // handle empty value
        if ($val === '') {
            return $result . $this->empty;
        }
        $result .= '=';
        if ($options['modifier'] === ':') {
            $val = \mb_substr($val, 0, (int) $options['value']);
        }
        return $result . $this->encode($parser, $var, $val);
    }
    public function expandNonExplode(Parser $parser, Node\Variable $var, array $val) : ?string
    {
        if (empty($val)) {
            return null;
        }
        $result = $this->encode($parser, $var, $var->name);
        $result .= '=';
        return $result . $this->encode($parser, $var, $val);
    }
    public function expandExplode(Parser $parser, Node\Variable $var, array $val) : ?string
    {
        if (empty($val)) {
            return null;
        }
        $list = isset($val[0]);
        $data = [];
        foreach ($val as $k => $v) {
            // if value is a list, use `varname` as keyname, otherwise use `key` name
            $key = $list ? $var->name : $k;
            if ($list) {
                $data[$key][] = $v;
            } else {
                $data[$key] = $v;
            }
        }
        // if it's array modifier, we have to use variable name as index
        // e.g. if variable name is 'query' and value is ['limit' => 1]
        // then we convert it to ['query' => ['limit' => 1]]
        if (!$list && $var->options['modifier'] === '%') {
            $data = [$var->name => $data];
        }
        return $this->encodeExplodeVars($var, $data);
    }
    public function extract(Parser $parser, Node\Variable $var, $data) : array|string
    {
        // get rid of optional `&` at the beginning
        if ($data[0] === '&') {
            $data = \substr($data, 1);
        }
        $value = $data;
        $vals = \explode($this->sep, $data);
        $options = $var->options;
        switch ($options['modifier']) {
            case '%':
                \parse_str($value, $query);
                return $query[$var->name];
            case '*':
                $value = [];
                foreach ($vals as $val) {
                    [$k, $v] = \explode('=', $val);
                    // 2
                    if ($k === $var->getToken()) {
                        $value[] = $v;
                    } else {
                        $value[$k] = $v;
                    }
                }
                break;
            case ':':
                break;
            default:
                // 1, 3
                // remove key from value e.g. 'lang=en,th' becomes 'en,th'
                $value = \str_replace($var->getToken() . '=', '', $value);
                $value = \explode(',', $value);
                if (\count($value) === 1) {
                    $value = \current($value);
                }
        }
        return $this->decode($parser, $var, $value);
    }
    public function encodeExplodeVars(Node\Variable $var, $data) : null|array|string
    {
        // http_build_query uses PHP_QUERY_RFC1738 encoding by default
        // i.e. spaces are encoded as '+' (plus signs) we need to convert
        // it to %20 RFC3986
        $query = \http_build_query($data, '', $this->sep);
        $query = \str_replace('+', '%20', $query);
        // `%` array modifier
        if ($var->options['modifier'] === '%') {
            // it also uses numeric based-index by default e.g. list[] becomes list[0]
            $query = \preg_replace('#%5B\\d+%5D#', '%5B%5D', $query);
        } else {
            // by default, http_build_query will convert array values to `a[]=1&a[]=2`
            // which is different from the spec. It should be `a=1&a=2`
            $query = \preg_replace('#%5B\\d+%5D#', '', $query);
        }
        // handle reserved charset
        if ($this->reserved) {
            $query = \str_replace(\array_keys(static::$reserved_chars), static::$reserved_chars, $query);
        }
        return $query;
    }
}
