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
    /** @var string Route start point */
    public $start_point;

    /** @var string Route end point */
    public $end_point;

    /** @var int Total route time in seconds */
    public $total_time;

    /** @var int Total route distance in meters */
    public $total_distance;
}
