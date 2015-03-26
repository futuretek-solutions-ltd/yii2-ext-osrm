<?php

namespace futuretek\osrm;

use futuretek\translations\Translator;
use yii\helpers\Json;

/**
 * Class Osrm
 *
 * @package osrm
 * @author  Lukas Cerny <lukas.cerny@futuretek.cz>
 * @license http://www.futuretek.cz/license FTSLv1
 * @link    http://www.futuretek.cz
 */
class Osrm
{
    use Translator;

    /**
     * @var string OSRM API URL
     */
    private $_apiUrl;

    /**
     * @var string OSRM API username
     */
    private $_username;

    /**
     * @var string OSRM API password
     */
    private $_password;

    /**
     * @var string CURL HTTP status code
     */
    private $_httpCode;

    /**
     * @var bool Use authorization
     */
    private $_useAuth = false;

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->_password;
    }

    /**
     * @return boolean
     */
    public function getUseAuth()
    {
        return $this->_useAuth;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->_username;
    }

    /**
     * Set authorization information
     *
     * Call it with no parameters to disable authorization
     *
     * @param null|string $username Username
     * @param null|string $password Password
     *
     * @return void
     */
    public function setAuthInfo($username = null, $password = null)
    {
        $this->_username = $username;
        $this->_password = $password;
        $this->_useAuth = ($this->_username != null && $this->_password != null);
    }

    /**
     * Test if the connection to the OSRM API is working
     *
     * @return int Connection status code (1 = OK, 0 = Reply mismatch, other = HTTP error code)
     */
    public function ping()
    {
        $query = $this->_apiUrl . 'hello';
        $response = Json::decode($this->_runQuery($query));

        if ($this->_httpCode == 200) {
            if ($response['title'] == 'Hello World') {
                return [
                    'status' => 'OK',
                    'response' => $response,
                ];
            } else {
                return [
                    'status' => 'ERROR',
                    'code' => '666',
                    'message' => self::t('osrm', 'Unexpected server reply.'),
                ];
            }
        } else {
            return [
                'status' => 'ERROR',
                'code' => $this->_httpCode,
                'message' => self::t('osrm', 'Error executing query. OSRM server returned HTTP code {code}', ['code' => $this->_httpCode]),
            ];
        }
    }

    /**
     * Run query
     *
     * @param string $query URL query string
     *
     * @return string HTTP status code (200 for OK)
     */
    private function _runQuery($query)
    {
        $c = curl_init($this->_apiUrl . $query);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);

        if ($this->_useAuth) {
            curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($c, CURLOPT_USERPWD, "{$this->_username}:{$this->_password}");
        }

        $response = curl_exec($c);
        $this->_httpCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);

        return Json::decode($response);
    }

    /**
     * Get API URL
     *
     * @return string API URL
     */
    public function getApiUrl()
    {
        return $this->_apiUrl;
    }

    /**
     * Set API URL
     *
     * @param string $url URL
     *
     * @return void
     */
    public function setApiUrl($url)
    {
        $this->_apiUrl = rtrim($url, '/') . '/';
    }

    public function getRoute($coords)
    {
        if (empty($coords) || !is_array($coords)) {
            return [
                'status' => 'ERROR',
                'code' => 667,
                'message' => self::t('osrm', 'No coordinates specified.'),
            ];
        }

        $query = 'viaroute?';
        foreach ($coords as $item) {
            $query .= 'loc=' . $item['lat'] . ',' . $item['lon'] . '&';
        }

        //Zoom (decrease if some routes cannot be found)
        $query .= 'z=13';

        $response = $this->_runQuery($query);

        if ($this->_httpCode == 200) {
            if ($response['status'] == 0) {
                return [
                    'status' => 'OK',
                    'response' => $response,
                ];
            } elseif ($response['status'] == 207) {
                return [
                    'status' => 'ERROR',
                    'code' => $response['status'],
                    'message' => self::t('osrm', 'No route found.'),
                ];
            } else {
                return [
                    'status' => 'ERROR',
                    'code' => 666,
                    'message' => self::t(
                        'osrm',
                        'Unknown OSRM status code {code}. Request was {req}',
                        ['code' => $response['status'], 'req' => $query]
                    ),
                ];
            }
        } else {
            return [
                'status' => 'ERROR',
                'code' => $this->_httpCode,
                'message' => self::t(
                    'osrm',
                    'Error executing query. OSRM server returned HTTP code {code}. Request was {req}',
                    ['code' => $this->_httpCode, 'req' => $query]
                ),
            ];
        }
    }

    /**
     * Get nearest node name
     *
     * @param string $gpsLat GPS Latitude
     * @param string $gpsLon GPS Longitude
     *
     * @return array
     */
    public function getNearest($gpsLat, $gpsLon)
    {
        $query = 'nearest?loc=' . $gpsLat . ',' . $gpsLon;
        $response = $this->_runQuery($query);

        if ($this->_httpCode == 200) {
            if ($response['status'] == 0) {
                return [
                    'status' => 'OK',
                    'response' => $response['name'],
                ];
            } elseif ($response['status'] == 207) {
                return [
                    'status' => 'ERROR',
                    'code' => $response['status'],
                    'message' => self::t('osrm', 'No nearest point found.'),
                ];
            } else {
                return [
                    'status' => 'ERROR',
                    'code' => 666,
                    'message' => self::t(
                        'osrm',
                        'Unknown OSRM status code {code}. Request was {req}',
                        ['code' => $response['status'], 'req' => $query]
                    ),
                ];
            }
        } else {
            return [
                'status' => 'ERROR',
                'code' => $this->_httpCode,
                'message' => self::t(
                    'osrm',
                    'Error executing query. OSRM server returned HTTP code {code}. Request was {req}',
                    ['code' => $this->_httpCode, 'req' => $query]
                ),
            ];
        }
    }

}
