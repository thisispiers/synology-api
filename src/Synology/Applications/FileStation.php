<?php

namespace Synology\Applications;

use Synology\Api\Authenticate;
use Synology\Exception;

/**
 * Class FileStation
 *
 * @see     http://ukdl.synology.com/download/Document/DeveloperGuide/Synology_File_Station_API_Guide.pdf
 * @package Synology\Applications
 */
class FileStation extends Authenticate
{
    const API_SERVICE_NAME = 'FileStation';
    const API_NAMESPACE = 'SYNO';

    /**
     * Info API setup
     *
     * @param string $address
     * @param int    $port
     * @param string $protocol
     * @param int    $version
     * @param bool   $verifySSL
     */
    public function __construct($address, $port = null, $protocol = null, $version = 1, $verifySSL = false)
    {
        parent::__construct(self::API_SERVICE_NAME, self::API_NAMESPACE, $address, $port, $protocol, $version, $verifySSL);
    }

    /**
     * Return Information about VideoStation
     * - is_manager
     * - version
     * - version_string
     */
    public function getInfo()
    {
        return $this->_request('Info', 'entry.cgi', 'get');
    }

    /**
     * Get Available Shares
     *
     * @param bool|string $onlyWritable
     * @param int|number  $limit
     * @param int|number  $offset
     * @param string      $sortBy
     * @param string      $sortDirection
     * @param bool        $additional
     *
     * @return array
     *
     * @throws Exception
     */
    public function getShares($onlyWritable = false, $limit = 25, $offset = 0, $sortBy = 'name', $sortDirection = 'asc', $additional = false)
    {
        return $this->_request('List', 'entry.cgi', 'list_share', [
            'onlywritable'   => $onlyWritable,
            'limit'          => $limit,
            'offset'         => $offset,
            'sort_by'        => $sortBy,
            'sort_direction' => $sortDirection,
            'additional'     => $additional ? 'real_path,owner,time,perm,volume_status' : ''
        ]);
    }

    /**
     * Get info about an object
     *
     * @param array $paths
     *
     * @return array
     *
     * @throws Exception
     */
    public function getFileInfo($paths, $additional = null)
    {
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        $additional = array_intersect($additional, [
            'real_path',
            'size',
            'owner',
            'time',
            'perm',
            'mount_point_type',
            'type',
        ]);
        return $this->_request('List', 'entry.cgi', 'getinfo', [
            'path' => $paths ? '["' . implode('","', $paths) . '"]' : '',
            'additional' => $additional ? '["' . implode('","', $additional) . '"]' : '',
        ]);
    }

    /**
     * Get a list of files/directories in a given path
     *
     * @param string     $path     like '/home'
     * @param int|number $limit
     * @param int|number $offset
     * @param string     $sortBy   (name|size|user|group|mtime|atime|ctime|crtime|posix|type)
     * @param string     $sortDirection
     * @param string     $pattern
     * @param string     $fileType (all|file|dir)
     * @param bool       $additional
     *
     * @return array
     * @throws Exception
     */
    public function getList($path = '/home', $limit = 25, $offset = 0, $sortBy = 'name', $sortDirection = 'asc', $pattern = '', $fileType = 'all', $additional = false)
    {
        return $this->_request('List', 'entry.cgi', 'list', [
            'folder_path'    => $path,
            'limit'          => $limit,
            'offset'         => $offset,
            'sort_by'        => $sortBy,
            'sort_direction' => $sortDirection,
            'pattern'        => $pattern,
            'filetype'       => $fileType,
            'additional'     => $additional ? 'real_path,size,owner,time,perm' : ''
        ]);
    }

    /**
     * Search for files/directories in a given path
     *
     * @param string     $pattern
     * @param string     $path          like '/home'
     * @param int|number $limit
     * @param int|number $offset
     * @param string     $sortBy        (name|size|user|group|mtime|atime|ctime|crtime|posix|type)
     * @param string     $sortDirection (asc|desc)
     * @param string     $fileType      (all|file|dir)
     * @param bool       $additional
     *
     * @return array
     * @throws Exception
     */
    public function search($pattern, $path = '/home', $limit = 25, $offset = 0, $sortBy = 'name', $sortDirection = 'asc', $fileType = 'all', $additional = false)
    {
        return $this->_request('List', 'entry.cgi', 'list', [
            'folder_path'    => $path,
            'limit'          => $limit,
            'offset'         => $offset,
            'sort_by'        => $sortBy,
            'sort_direction' => $sortDirection,
            'pattern'        => $pattern,
            'filetype'       => $fileType,
            'additional'     => $additional ? 'real_path,size,owner,time,perm' : ''
        ]);
    }

    /**
     * Download a file
     *
     * @param string $path (comma separated)
     * @param string $mode
     *
     * @return array
     */
    public function download($path, $mode = 'open')
    {
        return $this->_request('Download', 'entry.cgi', 'download', [
            'path' => $path,
            'mode' => $mode
        ]);
    }

    public function createFolder($folder_path, $name, $force_parent = false, $additional = false)
    {
        return $this->_request('CreateFolder', 'entry.cgi', 'create', [
            'folder_path'  => $folder_path,
            'name'         => $name,
            'force_parent' => $force_parent,
            'additional'   => $additional ? 'real_path,size,owner,time,perm' : ''
        ]);
    }

    public function upload($path, $content, $create_parents = false, $overwrite = null, $mtime = null, $crtime = null, $atime = null)
    {
        $api = 'Upload';
        $path = 'entry.cgi';
        $method = 'upload';

        $params = [
            'path' => $path,
            'create_parents' => $create_parents,
            'filename' => new CURLStringFile($content, $path),
        ];
        if ($overwrite !== null) { $params['overwrite'] = $overwrite; }
        if ($mtime !== null) { $params['mtime'] = $mtime; }
        if ($crtime !== null) { $params['crtime'] = $crtime; }
        if ($atime !== null) { $params['atime'] = $atime; }
        $params['api']     = $this->_getApiName($api);
        $params['version'] = $this->_version;
        $params['method']  = $method;

        // create a new cURL resource
        $ch = curl_init();

        $url = $this->_getBaseUrl() . $path;
        $this->log($url, 'Requested Url');
        $this->log($params, 'Post Variable');

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, self::CONNECT_TIMEOUT);

        // Verify SSL or not
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->_verifySSL ? 2 : 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->_verifySSL);

        // grab URL and pass it to the browser
        $result = curl_exec($ch);
        $info   = curl_getinfo($ch);

        $this->log($info['http_code'], 'Response code');
        if (200 == $info['http_code']) {
            if (preg_match('#(plain|text|json)#', $info['content_type'])) {
                return $this->_parseRequest($api, $path, $result);
            } else {
                return $result;
            }
        } else {
            curl_close($ch);
            if ($info['total_time'] >= (self::CONNECT_TIMEOUT / 1000)) {
                throw new Exception('Connection Timeout');
            } else {
                $this->log($result, 'Result');
                throw new Exception('Connection Error');
            }
        }

        // close cURL resource, and free up system resources
        curl_close($ch);
    }

    public function delete($paths, $recursive = false)
    {
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        return $this->_request('Delete', 'entry.cgi', 'delete', [
            'path' => $paths ? '["' . implode('","', $paths) . '"]' : '',
            'recursive' => $recursive,
        ]);
    }
}