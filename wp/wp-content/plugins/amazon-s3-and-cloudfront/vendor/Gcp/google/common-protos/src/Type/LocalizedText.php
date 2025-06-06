<?php

# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: google/type/localized_text.proto
namespace DeliciousBrains\WP_Offload_Media\Gcp\Google\Type;

use DeliciousBrains\WP_Offload_Media\Gcp\Google\Protobuf\Internal\GPBType;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\Protobuf\Internal\RepeatedField;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\Protobuf\Internal\GPBUtil;
/**
 * Localized variant of a text in a particular language.
 *
 * Generated from protobuf message <code>google.type.LocalizedText</code>
 */
class LocalizedText extends \DeliciousBrains\WP_Offload_Media\Gcp\Google\Protobuf\Internal\Message
{
    /**
     * Localized string in the language corresponding to `language_code' below.
     *
     * Generated from protobuf field <code>string text = 1;</code>
     */
    protected $text = '';
    /**
     * The text's BCP-47 language code, such as "en-US" or "sr-Latn".
     * For more information, see
     * http://www.unicode.org/reports/tr35/#Unicode_locale_identifier.
     *
     * Generated from protobuf field <code>string language_code = 2;</code>
     */
    protected $language_code = '';
    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $text
     *           Localized string in the language corresponding to `language_code' below.
     *     @type string $language_code
     *           The text's BCP-47 language code, such as "en-US" or "sr-Latn".
     *           For more information, see
     *           http://www.unicode.org/reports/tr35/#Unicode_locale_identifier.
     * }
     */
    public function __construct($data = NULL)
    {
        \DeliciousBrains\WP_Offload_Media\Gcp\GPBMetadata\Google\Type\LocalizedText::initOnce();
        parent::__construct($data);
    }
    /**
     * Localized string in the language corresponding to `language_code' below.
     *
     * Generated from protobuf field <code>string text = 1;</code>
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }
    /**
     * Localized string in the language corresponding to `language_code' below.
     *
     * Generated from protobuf field <code>string text = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setText($var)
    {
        GPBUtil::checkString($var, True);
        $this->text = $var;
        return $this;
    }
    /**
     * The text's BCP-47 language code, such as "en-US" or "sr-Latn".
     * For more information, see
     * http://www.unicode.org/reports/tr35/#Unicode_locale_identifier.
     *
     * Generated from protobuf field <code>string language_code = 2;</code>
     * @return string
     */
    public function getLanguageCode()
    {
        return $this->language_code;
    }
    /**
     * The text's BCP-47 language code, such as "en-US" or "sr-Latn".
     * For more information, see
     * http://www.unicode.org/reports/tr35/#Unicode_locale_identifier.
     *
     * Generated from protobuf field <code>string language_code = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setLanguageCode($var)
    {
        GPBUtil::checkString($var, True);
        $this->language_code = $var;
        return $this;
    }
}
