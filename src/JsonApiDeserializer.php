<?php

namespace Bridit\JsonApiDeserializer;

/**
 * Class JsonApiDeserializer
 * @package Bridit\JsonApiRepository
 */
class JsonApiDeserializer
{

  /**
   * @param $serialized
   * @return array|mixed|null
   */
  public static function deserialize($serialized)
  {
    $isResource = static::isResource($serialized->data);
    $deserialized = $isResource
      ? static::deserializeResource($serialized, null)
      : static::deserializeCollection($serialized, null);

    $deserialized = is_object($deserialized) && property_exists($deserialized, 'data') ? $deserialized->data : $deserialized;

    return $isResource ? $deserialized : (array) $deserialized;
  }

  /**
   * @param $value
   * @return bool
   */
  protected static function isResource($value): bool
  {
    return is_object($value) && property_exists($value, 'id') && property_exists($value, 'type');
  }

  /**
   * @param $object
   * @param array|null $included
   * @return mixed
   */
  protected static function deserializeResource($object, ?array $included = null)
  {
    $included = $included ?? (property_exists($object, 'included') ? $object->included : []);

    $data = is_object($object) && property_exists($object, 'data') ? $object->data : $object;

    if (!is_object($data) || !property_exists($data, 'relationships')) {
      return $object;
    }

    $data->relationships = (object) array_map(function ($relationship) use ($included) {

      if (empty($included) ||
        (!is_object($relationship) && !is_array($relationship)) ||
        (!is_object($relationship->data) && !is_array($relationship->data))
      ) {
        return is_object($relationship) && property_exists($relationship, 'data') ? $relationship->data : $relationship;
      }

      $relationshipAttributes = $relationship->data;

      if (static::isResource($relationshipAttributes)) {
        return static::deserializeResource(static::filterIncludes($relationshipAttributes, $included), $included);
      }

      return array_map(function ($relationshipAttributes) use($included) {
        return static::deserializeResource(static::filterIncludes($relationshipAttributes, $included), $included);
      }, (array) $relationshipAttributes);

    }, (array) $data->relationships);

    if (is_object($object) && property_exists($object, 'data')) {
      $object->data = $data;
    } else {
      $object = $data;
    }

    return $object;
  }

  protected static function deserializeCollection($object, ?array $included)
  {
    $included = $included ?? (property_exists($object, 'included') ? $object->included : []);

    $object->data = (object) array_map(function ($item) use ($included) {

      return static::deserializeResource($item, $included);

    }, (array) $object->data);

    return $object;
  }

  protected static function filterIncludes($relationshipAttributes, $included)
  {
    $result = array_values(array_filter($included, function ($value) use ($relationshipAttributes) {
      return $value->type === $relationshipAttributes->type && $value->id === $relationshipAttributes->id;
    }));

    return isset($result[0]) ? $result[0] : $result;
  }
}
