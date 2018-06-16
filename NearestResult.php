<?php

namespace futuretek\osrm;

use yii\base\BaseObject;

/**
 * Class NearestResult
 *
 * @package futuretek\osrm
 * @author  Lukas Cerny <lukas.cerny@futuretek.cz>
 * @license proprietary
 * @link    http://www.futuretek.cz
 */
class NearestResult extends BaseObject
{
    /** @var string Node name */
    public $name;

    /** @var array Mapped coordinates - [Latitude, Longitude] */
    public $mapped_coordinate;
}
