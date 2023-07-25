<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api;

/**
 * Base class representing a modeled shape.
 */
class Shape extends AbstractModel
{
    /**
     * Get a concrete shape for the given definition.
     *
     * @param array    $definition
     * @param ShapeMap $shapeMap
     *
     * @return mixed
     * @throws \RuntimeException if the type is invalid
     */
    public static function create(array $definition, ShapeMap $shapeMap)
    {
        static $map = ['structure' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\StructureShape', 'map' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\MapShape', 'list' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\ListShape', 'timestamp' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\TimestampShape', 'integer' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\Shape', 'double' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\Shape', 'float' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\Shape', 'long' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\Shape', 'string' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\Shape', 'byte' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\Shape', 'character' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\Shape', 'blob' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\Shape', 'boolean' => 'DeliciousBrains\\WP_Offload_Media\\Aws3\\Aws\\Api\\Shape'];
        if (isset($definition['shape'])) {
            return $shapeMap->resolve($definition);
        }
        if (!isset($map[$definition['type']])) {
            throw new \RuntimeException('Invalid type: ' . \print_r($definition, \true));
        }
        $type = $map[$definition['type']];
        return new $type($definition, $shapeMap);
    }
    /**
     * Get the type of the shape
     *
     * @return string
     */
    public function getType()
    {
        return $this->definition['type'];
    }
    /**
     * Get the name of the shape
     *
     * @return string
     */
    public function getName()
    {
        return $this->definition['name'];
    }
    /**
     * Get a context param definition.
     */
    public function getContextParam()
    {
        return $this->contextParam;
    }
}
