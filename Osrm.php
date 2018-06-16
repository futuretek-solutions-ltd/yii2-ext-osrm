<?php

namespace futuretek\osrm;

use futuretek\yii\shared\FtsException;
use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\web\HttpException;

/**
 * Class Osrm
 *
 * @package osrm
 * @author  Lukas Cerny <lukas.cerny@futuretek.cz>
 * @license proprietary
 * @link    http://www.futuretek.cz
 */
class Osrm extends BaseObject
{
    /** @var string OSRM API URL */
    public $url;

    /** @var string OSRM API username */
    public $username;

    /** @var string OSRM API password */
    public $password;

    /** @var resource cURL handler */
    private $_curl;

    /**
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if ($this->url === null) {
            throw new InvalidConfigException(Yii::t('fts-yii2-osrm', 'OSRM API URL not set.'));
        }
        $this->url = rtrim($this->url, '/') . '/';

        //Init cURL
        $this->_curl = curl_init();
        curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, 1);
        if ($this->username !== null && $this->password !== null) {
            curl_setopt($this->_curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($this->_curl, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        }
    }


    /**
     * Test if the connection to the OSRM API is working
     *
     * @return bool
     * @throws \yii\web\HttpException
     * @throws \yii\base\InvalidParamException
     */
    public function ping()
    {
        $response = $this->_runQuery('hello');

        return array_key_exists('check_sum', $response);
    }

    /**
     * Get route between points
     *
     * @param array $coordinates List of points. Each point must provide "lat" and "lon" element in form of associative array.
     * @param int $zoom Zoom (decrease if some routes cannot be found)
     * @return RouteResult
     * @throws \futuretek\yii\shared\FtsException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\base\InvalidParamException
     * @throws HttpException
     */
    public function getRoute(array $coordinates, $zoom = 13)
    {
        if (count($coordinates) < 2) {
            throw new FtsException(Yii::t('fts-yii2-osrm', 'Minimal two points should be provided.'));
        }

        $query = 'viaroute?';
        foreach ($coordinates as $item) {
            $query .= 'loc=' . $item['lat'] . ',' . $item['lon'] . '&';
        }

        $response = $this->_runQuery($query . 'z=' . $zoom);
        if ((int)$response['status'] !== 0) {
            throw new FtsException(Yii::t('fts-yii2-osrm', 'Error while executing route query: {msg}.', ['msg' => $response['status_message']]), (int)$response['status']);
        }

        return Yii::createObject(array_merge(['class' => '\futuretek\osrm\RouteResult'], $response['route_summary']));
    }

    /**
     * Get nearest node name
     *
     * @param string $gpsLat GPS Latitude
     * @param string $gpsLon GPS Longitude
     * @return NearestResult
     * @throws \futuretek\yii\shared\FtsException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\HttpException
     * @throws \yii\base\InvalidParamException
     */
    public function getNearest($gpsLat, $gpsLon)
    {
        $response = $this->_runQuery('nearest?loc=' . $gpsLat . ',' . $gpsLon);
        if ((int)$response['status'] !== 0) {
            throw new FtsException(Yii::t('fts-yii2-osrm', 'Error while executing route query: {msg}', ['msg' => $response['status_message']]), (int)$response['status']);
        }
        unset($response['status']);

        return Yii::createObject(array_merge(['class' => '\futuretek\osrm\NearestResult'], $response));
    }

    /**
     * Run query
     *
     * @param string $query URL query string
     * @return array Response
     * @throws \yii\base\InvalidParamException
     * @throws \yii\web\HttpException
     */
    private function _runQuery($query)
    {
        $query = $this->url . $query;
        Yii::trace('Running query: ' . $query, 'osrm');
        curl_setopt($this->_curl, CURLOPT_URL, $query);
        $response = curl_exec($this->_curl);
        $httpCode = curl_getinfo($this->_curl, CURLINFO_HTTP_CODE);
        Yii::trace('Query result code: ' . $httpCode, 'osrm');
        if ($httpCode !== 200 || !$response) {
            Yii::trace('Response from OSRM: ' . $response, 'osrm');
            throw new HttpException($httpCode, Yii::t('fts-yii2-osrm', 'Error while executing route query.'));
        }

        return Json::decode($response);
    }
}
