<?php

namespace futuretek\osrm;

use yii\base\BaseObject;

/**
 * Class RouteResult
 *
 * @package futuretek\osrm
 * @author  Lukas Cerny <lukas.cerny@futuretek.cz>
 * @license proprietary
 * @link    http://www.futuretek.cz
 */
class RouteResult extends BaseObject
{
    /** @var float The distance traveled by the route, in float meters. */
    public $distance;

    /** @var float The estimated travel time, in float number of seconds. */
    public $duration;

    /** @var array The whole geometry of the route value depending on overview parameter, format depending on the geometries parameter. See RouteStep's geometry property for a parameter documentation. */
    public $geometry;

    /** @var float The calculated weight of the route. */
    public $weight;

    /** @var string The name of the weight profile used during extraction phase. */
    public $weight_name;

    //Old properties

    /**
     * @var string Route start point
     * @deprecated
     */
    public $start_point;

    /**
     * @var string Route end point
     * @deprecated
     */
    public $end_point;

    /**
     * @var int Total route time in seconds
     * @deprecated
     */
    public $total_time;

    /**
     * @var int Total route distance in meters
     * @deprecated
     */
    public $total_distance;
}
