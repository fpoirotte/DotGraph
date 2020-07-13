<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

abstract class AbstractAccessors {
    static protected $getter_attributes = null;

    public function __get($attr)
    {
        $hierarchy = class_parents($this);
        array_unshift($hierarchy, static::class);

        foreach ($hierarchy as $cls) {
            $real = $cls::$getter_attributes[$attr] ?? null;
            if ($real !== null) {
                return $this->$real ?? null;
            }
        }
        return null;
    }
}
