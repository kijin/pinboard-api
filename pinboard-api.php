<?php

/**
 * Pinboard API Client in PHP
 * 
 * URL: http://github.com/kijin/pinboard-api
 * Version: 0.3.2
 * 
 * Copyright (c) 2012-2016, Kijin Sung <kijin@kijinsung.com>
 * Copyright (c) 2014, Erin Dalzell <erin@thedalzells.org>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class PinboardAPI
{
    // Settings are stored here.
    
    const API_BASE_URL = 'https://api.pinboard.in/v1/';
    const API_CLIENT_VERSION = '0.3.2';
    const ALLOWED_URL_SCHEMES_REGEX = '/^(?:https?|javascript|mailto|ftp|file):/i';
    const RECENT_COUNT_MAX = 100;
    const USER_AGENT = 'Mozilla/5.0 (Pinboard API Client %s for PHP; http://github.com/kijin/pinboard-api)';
    
    public static $_instance_hashes = array();
    protected $_instance_hash;
    protected $_user;
    protected $_pass;
    protected $_curl_handle;
    protected $_connection_timeout;
    protected $_request_timeout;
    protected $_last_status;
    protected $_logging_callback;
    
    // Constructor.
    
    public function __construct($user, $pass, $connection_timeout = 10, $request_timeout = 30)
    {
        $this->_user = $user;
        $this->_pass = $pass;
        $this->_connection_timeout = $connection_timeout;
        $this->_request_timeout = $request_timeout;
        $this->_instance_hash = substr(md5($user . ':' . $pass), 0, 8);
        self::$_instance_hashes[$this->_instance_hash] = $this;
    }
    
    // Destructor.
    
    public function __destruct()
    {
        if (!is_null($this->_curl_handle)) curl_close($this->_curl_handle);
        unset(self::$_instance_hashes[$this->_instance_hash]);
    }
    
    // Enable logging to a user-specified function.
    
    public function enable_logging($func)
    {
        if (!is_callable($func))
        {
            throw new PinboardException('Argument is not a callable function or method');
        }
        $this->_logging_callback = $func;
    }
    
    // Call this before get_all() to check for updates.
    
    public function get_updated_time()
    {
        $json = $this->_remote('posts/update');
        $timestamp = $json->update_time;
        return strtotime($timestamp);
    }
    
    // Get recent bookmarks.
    
    public function get_recent($count = 15, $tags = null)
    {
        if ($count > self::RECENT_COUNT_MAX)
        {
            throw new PinboardException('Maximum permitted count is ' . self::RECENT_COUNT_MAX);
        }
        
        $args['count'] = (int)$count;
        if (!is_null($tags)) $args['tag'] = $this->_normalize_tags($tags);
        
        $json = $this->_remote('posts/recent', $args);
        return $this->_json_to_bookmark($json);
    }
    
    // Get all bookmarks.
    
    public function get_all($count = null, $offset = null, $tags = null, $from = null, $to = null)
    {
        $args = array();
        if (!is_null($count) && $count > 0) $args['results'] = (int)$count;
        if (!is_null($offset) && $offset > 0) $args['start'] = (int)$offset;
        if (!is_null($tags) && !empty($tags)) $args['tag'] = $this->_normalize_tags($tags);
        if (!is_null($from)) $args['fromdt'] = $this->_to_datetime($from);
        if (!is_null($to)) $args['todt'] = $this->_to_datetime($to);
        
        $json = $this->_remote('posts/all', $args);
        return $this->_json_to_bookmark($json);
    }
    
    // Get some bookmarks.
    
    public function get($url = null, $tags = null, $date = null)
    {
        $args = array();
        if (!is_null($url)) $args['url'] = $url;
        if (!is_null($tags)) $args['tag'] = $this->_normalize_tags($tags);
        if (!is_null($date)) $args['dt'] = substr($this->_to_datetime($date), 0, 10);
        
        $json = $this->_remote('posts/get', $args);
        return $this->_json_to_bookmark($json);
    }
    
    // Some shortcuts to the above.
    
    public function search_by_url($url)
    {
        return $this->get($url);
    }
    
    public function search_by_tag($tags)
    {
        return $this->get_all(null, null, $tags);
    }
    
    public function search_by_date($date)
    {
        return $this->get(null, null, $date);
    }
    
    public function search_by_interval($from, $to)
    {
        return $this->get_all(null, null, null, $from, $to);
    }
    
    // Save a new bookmark. (This include both adding and editing.)
    
    public function save($bookmark, $replace = true)
    {
        if (!($bookmark instanceof PinboardBookmark))
        {
            throw new PinboardException('Argument is not an instance of PinboardBookmark');
        }
        if (empty($bookmark->url))
        {
            throw new PinboardException('URL is required');
        }
        if (empty($bookmark->title))
        {
            throw new PinboardException('Title is required');
        }
        if (!preg_match(self::ALLOWED_URL_SCHEMES_REGEX, $bookmark->url))
        {
            throw new PinboardException('Invalid URL');
        }
        
        $args = array(
            'url' => $bookmark->url,
            'description' => $bookmark->title,
            'extended' => $bookmark->description,
            'tags' => $this->_normalize_tags($bookmark->tags),
            'replace' => $replace ? 'yes' : 'no',
        );
        if (!is_null($bookmark->timestamp)) $args['dt'] = $this->_to_datetime($bookmark->timestamp);
        if (!is_null($bookmark->is_public)) $args['shared'] = $bookmark->is_public ? 'yes' : 'no';
        if (!is_null($bookmark->is_unread)) $args['toread'] = $bookmark->is_unread ? 'yes' : 'no';
        
        $json = $this->_remote('posts/add', $args);
        return $this->_json_to_status($json);
    }
    
    // Delete a bookmark.
    
    public function delete($bookmark)
    {
        if ($bookmark instanceof PinboardBookmark)
        {
            $bookmark = $bookmark->url;
        }
        
        $json = $this->_remote('posts/delete', array('url' => $bookmark));
        return $this->_json_to_status($json);
    }
    
    // Get dates.
    
    public function get_dates($tags = null)
    {
        $args = array();
        if (!is_null($tags)) $args['tag'] = $this->_normalize_tags($tags);
        
        $json = $this->_remote('posts/dates', $args);
        $ret = array();
        foreach ($json->dates as $date => $count)
        {
            $ret[] = new PinboardDate($date, (int)$count);
        }
        return $ret;
    }
    
    // Get tag suggestions for a bookmark.
    
    public function get_suggested_tags($bookmark)
    {
        if ($bookmark instanceof PinboardBookmark)
        {
            $bookmark = $bookmark->url;
        }
        
        $json = $this->_remote('posts/suggest', array('url' => $bookmark));
        return (object)array(
            'popular' => isset($json[0]->popular) ? $json[0]->popular : array(),
            'recommended' => isset($json[1]->recommended) ? $json[1]->recommended : array(),
        );
    }
    
    // Get all tags.
    
    public function get_tags()
    {
        $json = $this->_remote('tags/get');
        $ret = array();
        foreach ($json as $tag => $count)
        {
            $ret[] = new PinboardTag((string)$tag, (int)$count);
        }
        return $ret;
    }
    
    // Rename a tag.
    
    public function rename_tag($old, $new)
    {
        $json = $this->_remote('tags/rename', array('old' => (string)$old, 'new' => (string)$new));
        return $this->_json_to_status($json);
    }
    
    // Delete a tag.
    
    public function delete_tag($tag)
    {
        $json = $this->_remote('tags/delete', array('tag' => (string)$tag));
        return $this->_json_to_status($json);
    }
    
    // Get the list of notes.
    
    public function list_notes()
    {
        $json = $this->_remote('notes/list');
        return $this->_json_to_note($json);
    }
    
    // Get a single note.
    
    public function get_note($id)
    {
        if (!preg_match('/^[0-9a-f]{20}$/i', $id)) return false;
        $json = $this->_remote('notes/' . $id);
        $note = $this->_json_to_note($json);
        return count($note) ? current($note) : false;
    }
    
    // Get the user's secret RSS token.
    
    public function get_rss_token()
    {
        $json = $this->_remote('user/secret');
        return isset($json->result) ? $json->result : null;
    }
    
    // Get the user's API token.
    
    public function get_api_token()
    {
        $json = $this->_remote('user/api_token');
        return isset($json->result) ? $json->result : null;
    }
    
    // Get the last status code.
    
    public function get_last_status()
    {
        return $this->_last_status;
    }
    
    // Dump all your bookmarks in an importable format.
    
    public function dump()
    {
        return $this->_remote('posts/all', false, false);
    }
    
    // This method handles all remote method calls.
    
    protected function _remote($method, $args = array(), $use_json = true)
    {
        if ($this->_user === null || preg_match('/^' . preg_quote($this->_user, '/') . ':[0-9A-F]{20}$/i', $this->_pass))
        {
            $args['auth_token'] = $this->_pass;
            $use_http_auth = false;
        }
        else
        {
            $use_http_auth = true;
        }
        
        if ($use_json)
        {
            $args['format'] = 'json';
        }
        
        if (is_array($args) && count($args))
        {
            $querystring = '?' . http_build_query($args);
        }
        else
        {
            $querystring = '';
        }
        
        $url = self::API_BASE_URL . $method . $querystring;
        if (!is_null($this->_logging_callback))
        {
            $func = $this->_logging_callback;
            $func($url);
        }
        
        if (is_null($this->_curl_handle))
        {
            $this->_curl_handle = curl_init();
            curl_setopt_array($this->_curl_handle, array(
                CURLOPT_CONNECTTIMEOUT => $this->_connection_timeout,
                CURLOPT_TIMEOUT => $this->_request_timeout,
                CURLOPT_USERAGENT => sprintf(self::USER_AGENT, self::API_CLIENT_VERSION),
                CURLOPT_ENCODING => '',
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_MAXREDIRS => 1,
            ));
            if ($use_http_auth)
            {
                curl_setopt($this->_curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                curl_setopt($this->_curl_handle, CURLOPT_USERPWD, $this->_user . ':' . $this->_pass);
            }
        }
        
        curl_setopt($this->_curl_handle, CURLOPT_URL, $url);
        $response = curl_exec($this->_curl_handle);
        $status = (int)curl_getinfo($this->_curl_handle, CURLINFO_HTTP_CODE);
        
        switch ($status)
        {
            case 200: break;
            case 401:
                throw new PinboardException_AuthenticationFailure('Authentication failure (using ' . ($use_http_auth ? 'password' : 'token'));
            case 429:
                throw new PinboardException_TooManyRequests('Too many requests');
            default:
                if ($status > 0) throw new PinboardException_InvalidResponse('Server responded with HTTP status code ' . $status);
                if (curl_errno($this->_curl_handle)) throw new PinboardException_ConnectionError(curl_error($this->_curl_handle));
                throw new PinboardException_ConnectionError('Unknown error');
        }
        
        if ($use_json)
        {
            $json = @json_decode($response);
            if ($json === false || $json === null) throw new PinboardException_InvalidResponse('Invalid JSON response');
            return $json;
        }
        else
        {
            return $response;
        }
    }
    
    // Normalize tags.
    
    protected function _normalize_tags($tags)
    {
        $ret = array();
        if (is_array($tags))
        {
            foreach ($tags as $tag)
            {
                $ret[] = trim($tag);
            }
        }
        else
        {
            $ret = preg_split('/\\s+/', trim($tags));
        }
        return trim(implode(' ', $ret));
    }
    
    // This method translates timestamps into Pinboard API's datetime format.
    
    protected function _to_datetime($timestamp)
    {
        if (!is_int($timestamp) && !ctype_digit($timestamp)) $timestamp = strtotime(trim($timestamp));
        return gmdate('Y-m-d\\TH:i:s\\Z', $timestamp);
    }
    
    // This method builds a PinboardBookmark object from an XML element.
    
    protected function _json_to_bookmark($json)
    {
        $ret = array();
        if (is_object($json) && isset($json->posts))
        {
            $json = $json->posts;
        }
        if (!is_array($json))
        {
            return array();
        }
        
        foreach ($json as $entry)
        {
            if (!isset($entry->href)) continue;
            $bookmark = new PinboardBookmark;
            $bookmark->_api_instance_hash = $this->_instance_hash;
            $bookmark->url = $entry->href;
            $bookmark->title = $entry->description;
            $bookmark->description = $entry->extended;
            $bookmark->timestamp = strtotime($entry->time);
            $bookmark->tags = explode(' ', $entry->tags);
            $bookmark->hash = $entry->hash;
            $bookmark->meta = $entry->meta;
            $bookmark->others = isset($entry->others) ? (int)$entry->others : 0;
            $bookmark->is_public = ($entry->shared === 'yes');
            $bookmark->is_unread = ($entry->toread === 'yes');
            $ret[] = $bookmark;
        }
        
        return $ret;
    }
    
    // This method builds a PinboardNote object from an XML element.
    
    protected function _json_to_note($json)
    {
        $ret = array();
        if (isset($json->notes)) $json = $json->notes;
        if (is_object($json)) $json = array($json);
        
        foreach ($json as $entry)
        {
            if (!isset($entry->id)) continue;
            $note = new PinboardNote;
            $note->id = (string)$entry->id;
            if (isset($entry->title)) $note->title = (string)$entry->title;
            if (isset($entry->hash)) $note->hash = (string)$entry->hash;
            if (isset($entry->created_at)) $note->created_at = (string)$entry->created_at;
            if (isset($entry->updated_at)) $note->updated_at = (string)$entry->updated_at;
            if (isset($entry->length)) $note->length = (string)$entry->length;
            if (isset($entry->text)) $note->text = (string)$entry->text;
            $ret[] = $note;
        }
        
        return $ret;
    }
    
    // This method translates XML responses into boolean status codes.
    
    protected function _json_to_status($json)
    {
        $status = '';
        if (isset($json->result_code)) $status = strval($json->result_code);
        if (!$status && isset($json->result)) $status = strval($json->result);
        $this->_last_status = $status;
        return (bool)($status === 'done');
    }
}

// Bookmark class, used for handling individual bookmarks.

class PinboardBookmark
{
    // This property may be public, but it is not to be touched.
    
    public $_api_instance_hash;
    
    // These properties are free to modify.
    
    public $url;
    public $title;
    public $description;
    public $timestamp;
    public $tags = array();
    public $is_public;
    public $is_unread;
    
    // These properties will not be saved.
    
    public $others;
    public $hash;
    public $meta;
    
    // Save shortcut.
    
    public function save($api_instance = null)
    {
        $save_to = $this->_find_api_instance($api_instance);
        return $save_to->save($this);
    }
    
    // Delete shortcut.
    
    public function delete($api_instance = null)
    {
        $save_to = $this->_find_api_instance($api_instance);
        return $save_to->delete($this);
    }
    
    // Magically find out which API instance to save to, if there are more than one.
    
    protected function _find_api_instance($hint)
    {
        if ($hint instanceof PinboardAPI)
        {
            return $hint;
        }
        elseif (count(PinboardAPI::$_instance_hashes) == 1)
        {
            $instances = array_values(PinboardAPI::$_instance_hashes);
            return $instances[0];
        }
        elseif (!is_null($this->_api_instance_hash)
            && array_key_exists($this->_api_instance_hash, PinboardAPI::$_instance_hashes)
            && PinboardAPI::$_instance_hashes[$this->_api_instance_hash] instanceof PinboardAPI)
        {
            return PinboardAPI::$_instance_hashes[$this->_api_instance_hash];
        }
        else
        {
            throw new PinboardException('Multiple instance of PinboardAPI are running. Please specify which instance to save to!');
        }
    }
}

// Date class, used in get_dates().

class PinboardDate
{
    public $date;
    public $count;
    
    public function __construct($date, $count = null)
    {
        $this->date = trim($date);
        $this->count = $count;
    }
    
    public function __toString()
    {
        return $this->date;
    }
}

// Tag class, used in get_tags().

class PinboardTag
{
    public $tag;
    public $count;
    
    public function __construct($tag, $count = null)
    {
        $this->tag = trim($tag);
        $this->count = $count;
    }
    
    public function __toString()
    {
        return $this->tag;
    }
}

// Note class, used for handling individual notes.

class PinboardNote
{
    public $id;
    public $title;
    public $hash;
    public $created_at;
    public $updated_at;
    public $length;
    public $text;
}

// Exceptions.

class PinboardException extends Exception { }
class PinboardException_ConnectionError extends PinboardException { }
class PinboardException_AuthenticationFailure extends PinboardException { }
class PinboardException_TooManyRequests extends PinboardException { }
class PinboardException_InvalidResponse extends PinboardException { }
