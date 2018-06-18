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
    const VERSION = 'v1';
    const PROFILE = 'car';
    const FORMAT = 'json';

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
     * @deprecated Not implemented in API anymore
     */
    public function ping()
    {
        return true;
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

        $response = $this->_runQuery('route', $coordinates, ['zoom' => $zoom]);

        if (!array_key_exists('routes', $response) || !is_array($response['routes'])) {
            throw new HttpException(Yii::t('fts-yii2-osrm', 'Invalid response.'));
        }

        $route = reset($response['routes']);
        $firstPoint = reset($coordinates);
        $lastPoint = end($coordinates);

        $route['start_point'] = [$firstPoint['lat'], $firstPoint['lon']];
        $route['end_point'] = [$lastPoint['lat'], $lastPoint['lon']];
        $route['total_time'] = $route['duration'];
        $route['total_distance'] = $route['distance'];

        return Yii::createObject(array_merge(['class' => '\futuretek\osrm\RouteResult'], $route));
    }

    /**
     * Get nearest node name
     *
     * @param string $gpsLat GPS Latitude
     * @param string $gpsLon GPS Longitude
     * @return NearestResult
     * @throws \futuretek\yii\shared\FtsException
     * @throws \yii\web\HttpException
     * @throws \yii\base\InvalidParamException
     */
    public function getNearest($gpsLat, $gpsLon)
    {
        $response = $this->_runQuery('nearest', [['lat' => $gpsLat, 'lon' => $gpsLon]]);

        if (!array_key_exists('waypoints', $response) || !is_array($response['waypoints'])) {
            throw new HttpException(Yii::t('fts-yii2-osrm', 'Invalid response.'));
        }

        $wp = reset($response['waypoints']);
        $wp['mapped_coordinate'] = [$wp['location'][1], $wp['location'][0]];

        return Yii::createObject(array_merge(['class' => '\futuretek\osrm\NearestResult'], $wp));
    }

    /**
     * Build URL part from coordinates array
     *
     * @param array $coordinates List of points. Each point must provide "lat" and "lon" element in form of associative array.
     * @return string
     * @throws FtsException
     */
    protected function buildLogLat(array $coordinates)
    {
        $output = [];
        foreach ($coordinates as $coord) {
            if (!array_key_exists('lon', $coord) || !array_key_exists('lat', $coord)) {
                throw new FtsException(Yii::t('fts-yii2-osrm', 'Invalid coordinates format.'));
            }
            $output[] = $coord['lon'] . ',' . $coord['lat'];
        }

        return implode(';', $output);
    }

    /**
     * Get error message specified by error code
     *
     * @param string $code Error code
     * @return string
     */
    protected function getErrorMessage($code)
    {
        switch ($code) {
            case 'Ok':
                return 'Request could be processed as expected.';
                break;
            case 'InvalidUrl':
                return 'URL string is invalid.';
                break;
            case 'InvalidService':
                return 'Service name is invalid.';
                break;
            case 'InvalidVersion':
                return 'Version is not found.';
                break;
            case 'InvalidOptions':
                return 'Options are invalid.';
                break;
            case 'InvalidQuery':
                return 'The query string is synctactically malformed.';
                break;
            case 'InvalidValue':
                return 'The successfully parsed query parameters are invalid.';
                break;
            case 'NoSegment':
                return 'One of the supplied input coordinates could not snap to street segment.';
                break;
            case 'TooBig':
                return 'The request size violates one of the service specific request size restrictions.';
                break;
            default:
                return 'Unknown error.';
        }
    }

    /**
     * Run query
     *
     * @param string $action API action
     * @param array $coordinates List of points. Each point must provide "lat" and "lon" element in form of associative array.
     * @param array Additional parameters (after question mark) in format name => value.
     * @return array Response
     * @throws \yii\base\InvalidParamException
     * @throws \yii\web\HttpException
     * @throws FtsException
     */
    private function _runQuery($action, array $coordinates, array $params = [])
    {
        $paramsArr = [];
        foreach ($params as $k => $v) {
            $paramsArr = $k . '=' . $v;
        }
        $paramsStr = implode('&', $paramsArr);

        $query = $this->url . $action . '/' . self::VERSION . '/' . self::PROFILE . '/' . $this->buildLogLat($coordinates) . '.' . self::FORMAT . ($paramsStr ? '?' . $paramsStr : '');
        Yii::trace('Running query: ' . $query, 'osrm');
        curl_setopt($this->_curl, CURLOPT_URL, $query);
        $response = curl_exec($this->_curl);
        $httpCode = curl_getinfo($this->_curl, CURLINFO_HTTP_CODE);
        Yii::trace('Query result code: ' . $httpCode, 'osrm');
        if ($httpCode !== 200 || !$response) {
            Yii::trace('Response from OSRM: ' . $response, 'osrm');
            if (!$response) {
                throw new HttpException($httpCode, Yii::t('fts-yii2-osrm', 'Error while executing route query.'));
            }

            $response = Json::decode($response);
            throw new HttpException($httpCode, Yii::t('fts-yii2-osrm', 'Error while executing route query: {msg}.', ['msg' => $this->getErrorMessage($response['code'])]));
        }

        return Json::decode($response);
    }
}
