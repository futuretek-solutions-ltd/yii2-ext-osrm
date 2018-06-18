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
    /**
     * @var string Unique internal identifier of the segment (ephemeral, not constant over data updates).
     * This can be used on subsequent request to significantly speed up the query and to connect multiple services.
     * E.g. you can use the hint value obtained by the nearest query as hint values for route inputs.
     */
    public $hint;

    /** @var string Distance from query coordinates */
    public $distance;

    /** @var string Name of the street the coordinate snapped to */
    public $name;

    /** @var array Array that contains the [longitude, latitude] pair of the snapped coordinate */
    public $location;

    /**
     * @var array Mapped coordinates - [Latitude, Longitude]
     * @deprecated
     */
    public $mapped_coordinate;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        //Backward compatibility
    }
}
