
Pinboard API Client in PHP
--------------------------

This library implements a client for [Pinboard API](https://pinboard.in/api/).
All of the XML juggling is abstracted away, so that you can work with native PHP arrays and objects.
Currently, all features of API v1 are supported.
However, this library has not been tested extensively, so caution is advised when using it on important data.

This library requires PHP 5 with the cURL extension. SimpleXML must also be enabled.

This library is released under the liberal [MIT License](http://opensource.org/licenses/MIT).


Getting Started
---------------

Here's some sample code:
    
    // Bootstrap.
    include 'pinboard-api.php';
    $pinboard = new PinboardAPI('username', 'password');
    
    // Create a new bookmark.
    $bookmark = new PinboardBookmark;
    $bookmark->url = 'https://pinboard.in';
    $bookmark->title = 'Pinboard';
    $bookmark->description = 'An awesome bookmarking service';
    $bookmark->tags = array('awesome', 'bookmarking');
    $pinboard->save($bookmark);
    
    // Edit a bookmark.
    $bookmark = $pinboard->search_by_url('https://delicious.com/');
    $bookmark->description = 'Not so tasty anymore';
    $pinboard->save($bookmark);
    
    // Get a list of your tags.
    $tags = $pinboard->get_tags();
    foreach ($tags as $tag) {
        echo "{$tag->count} bookmarks are tagged '{$tag}'.\n";
    }

    
Classes and Methods
-------------------


### PinboardAPI->__construct()

**Arguments :**

    - _required_ **$user** : your Pinboard username.
    - _required_ **$pass** : your Pinboard password.
    - _optional_ **$connection_timeout** : connection timeout in seconds. Default is 10.
    - _optional_ **$request_timeout** : request timeout in seconds. Default is 30.

    
### PinboardAPI->enable_logging()

Use this method if you would like to get notified every time the API Client makes a remote request.
This can be useful for debugging.

**Arguments :**

    - _required_ **$func** : a callable that takes one argument.

The callable can be either a function name, a method name, or a closure.
It will be passed the remote URL whenever the API Client makes a request to Pinboard.


### PinboardAPI->get_updated_time()

Use this method to find out when you last made changes to your bookmarks.
This can help reduce unnecessary calls to expensive methods such as `get_all()`.

**API Method Call :** `posts/update`

**Arguments :** none.

**Returns :** integer (Unix timestamp).


### PinboardAPI->get_recent()

Use this method to grab your most recent bookmarks, optionally filtered by up to three tags.
Note that Pinboard may impose rate limiting on this method.

**API Method Call :** `posts/recent`

**Arguments :**

    - _optional_ **$count** : integer, how many bookmarks to return. Default is 15. Maximum is 100.
    - _optional_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.

**Returns :** an array of `PinboardBookmark` objects.


### PinboardAPI->get_all()

Use this method to grab all of your bookmarks, optionally filtered by up to three tags or a time interval.
Note that Pinboard may impose rate limiting on this method.

If you would like to skip any arguments, use `null` in place of the missing argument.

**API Method Call :** `posts/all`

**Arguments :**

    - _optional_ **$count** : integer, how many bookmarks to return. Default is all.
    - _optional_ **$offset** : integer, when used with `$count`, how many bookmarks to skip.
    - _optional_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.
    - _optional_ **$from** : a Unix timestamp, or any string that PHP can parse into a timestamp.
    - _optional_ **$to** : a Unix timestamp, or any string that PHP can parse into a timestamp.

**Returns :** an array of `PinboardBookmark` objects.


### PinboardAPI->get()

Use this method to grab some of your bookmarks, optionally filtered by up to three tags.
Please read Pinboard's [documentation](https://pinboard.in/api/#posts_get)
for details on how this method behaves when the date argument is absent.

If you would like to skip any arguments, use `null` in place of the missing argument.

**API Method Call :** `posts/get`

**Arguments :**

    - _optional_ **$url** : the exact URL of the bookmark to look for.
    - _optional_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.
    - _optional_ **$date** : a Unix timestamp, any string that PHP can parse into a timestamp, or a date in the format `YYYY-MM-DD`.

**Returns :** an array of `PinboardBookmark` objects.


### PinboardAPI->search_by_url()

A shortcut to `get()`.
Note that this method will return an array even if there is only one bookmark.

**Arguments :**

    - _required_ **$url** : the exact URL of the bookmark to look for.

**Returns :** an array of `PinboardBookmark` objects.


### PinboardAPI->search_by_tag()

A shortcut to `get_all()`.

**Arguments :**

    - _required_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.

**Returns :** an array of `PinboardBookmark` objects.


### PinboardAPI->search_by_date()

A shortcut to `get()`.

**Arguments :**

    - _required_ **$date** : a Unix timestamp, any string that PHP can parse into a timestamp, or a date in the format `YYYY-MM-DD`.

**Returns :** an array of `PinboardBookmark` objects.


### PinboardAPI->search_by_interval()

A shortcut to `get_all()`.

**Arguments :**

    - _required_ **$from** : a Unix timestamp, or any string that PHP can parse into a timestamp.
    - _required_ **$to** : a Unix timestamp, or any string that PHP can parse into a timestamp.

**Returns :** an array of `PinboardBookmark` objects.


### PinboardAPI->save()

Use this method to add a new bookmark or edit an existing bookmark.

**API Method Call :** `posts/add`

**Arguments :**

    - _required_ **$bookmark** : a `PinboardBookmark` object to save.
    - _optional_ **$replace** : set to `false` if you don't want to overwrite an existing bookmark with the same URL. Default is `true`.

**Returns :** `true` on success and `false` on failure. Call `get_last_status()` to read the error message in case of a failure.


### PinboardAPI->delete()

Use this method to delete a bookmark.

**API Method Call :** `posts/delete`

**Arguments :**

    - _required_ **$bookmark** : a `PinboardBookmark` object, or a URL.

**Returns :** `true` on success and `false` on failure. Call `get_last_status()` to read the error message in case of a failure.


### PinboardAPI->get_dates()

Use this method to get a list of dates on which you added bookmarks, with the number of bookmarks for each day.
Optionally filtered by up to three tags.

**API Method Call :** `posts/dates`

**Arguments :**

    - _optional_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.

**Returns :** an array of `PinboardDate` objects. (These objects behave like strings.)


### PinboardAPI->get_suggested_tags()

Use this method to get tag suggestions for a bookmark or URL.

**API Method Call :** `posts/suggest`

**Arguments :**

    - _required_ **$bookmark** : a `PinboardBookmark` object, or a URL.

**Returns :** an associative array with two keys, `popular` and `recommended`, each of which contains an array of strings.


### PinboardAPI->get_tags()

Use this method to get a list of all your tags, with the number of bookmarks for each tag.

**API Method Call :** `tags/get`

**Arguments :** none.

**Returns :** an array of `PinboardTag` objects. (These objects behave like strings.)


### PinboardAPI->rename_tag()

Use this method to rename one tag to another.

**API Method Call :** `tags/rename`

**Arguments :**

    - _required_ **$old** : the old name, as a string.
    - _required_ **$new** : the new name, as a string.

**Returns :** `true` on success and `false` on failure. Call `get_last_status()` to read the error message in case of a failure.


### PinboardAPI->delete_tag()

Use this method to delete a tag. Bookmarks will not be deleted.

**API Method Call :** `tags/delete`

**Arguments :**

    - _required_ **$tag** : the tag to delete, as a string.

**Returns :** `true` on success and `false` on failure. Call `get_last_status()` to read the error message in case of a failure.


### PinboardAPI->get_rss_token()

Use this method to get your secret RSS token.

**API Method Call :** `user/secret`

**Arguments :** none.

**Returns :** a string containing your RSS token.


### PinboardAPI->get_last_status()

Use this method to retrieve any error message for one of the following methods: `save()`, `delete()`, `rename_tag()`, and `delete_tag()`.
If the last operation did not fail, this method will return "done".
If no applicable operation has been performed, this method will return `null`.

**Arguments :** none.

**Returns :** a string containing the last error message, or `null`.


### PinboardAPI->dump()

Use this method to back up all of your bookmarks.
The dump will be produced in an XML format that can be easily imported into Pinboard, Delicious,
or any other online bookmarking service that supports importing bookmarks in the Delicious format.
Note that Pinboard may impose rate limiting on this method.

**API Method Call :** `posts/all`

**Arguments :** none.

**Returns :** a string containing the XML dump.


### PinboardBookmark

This class is used with `save()` and several other methods that take bookmarks as an argument.
Its use is required when calling `save()`, but in most other cases it can be substituted with just a URL.
The API Client will also return instances of this class whenever it fetches bookmarks from Pinboard.

**Public Properties:**

    - _required_ **url** : the URL of the bookmark.
    - _required_ **title** : the title of the bookmark.
    - _optional_ **description** : any additional description.
    - _optional_ **timestamp** : a Unix timestamp, or any string that PHP can parse into a timestamp.
    - _optional_ **tags** : an array of 1-3 tags, or a string with spaces between tags.
    - _optional_ **is_public** : `true` or `false`. Default is determined by your Pinboard account settings.
    - _optional_ **is_unread** : `true` or `false`. Default is `false`.

The following properties are also public, but they will not be saved when you call `save()`.

    - **hash** : an MD5 hash of the URL that Pinboard uses to uniquely identify bookmarks.
    - **meta** : another hash that can be used to detect when a bookmark is changed.
    - **others** : the number of other Pinboard users who have bookmarked the same URL.


### PinboardDate

Instances of this class are returned by `get_dates()`. They contain dates in the format `YYYY-MM-DD`.
For all intents and purposes, these objects can be used exactly like strings.
The only difference is that it has a `count` property, which contains the number of bookmarks added on the corresponding date.

Examples:

    echo $date;         // prints '2012-03-20'
    echo $date->date;   // prints '2012-03-20'
    echo $date->count;  // prints '16'


### PinboardTag

Instances of this class are returned by `get_tags()`.
For all intents and purposes, these objects can be used exactly like strings.
The only difference is that it has a `count` property, which contains the number of bookmarks added on the corresponding date.

In order to conserve resources, this class is **not** used in other contexts,
such as the `tags` property of `PinboardBookmark` objects, the output of `get_suggested_tags()`,
or any other situation where the count is not relevant.

Examples:

    echo $tag;         // prints 'awesome'
    echo $tag->tag;    // prints 'awesome'
    echo $tag->count;  // prints '42'


### Exceptions

The Pinboard API Client defines the following exceptions:

    - `PinboardException` (extends `Exception`)
    - `PinboardException_ConnectionError` (extends `PinboardException`)
    - `PinboardException_TooManyRequests` (extends `PinboardException`)
    - `PinboardException_InvalidResponse` (extends `PinboardException`)

`PinboardException_ConnectionError` is thrown if there is a problem connecting to Pinboard's servers.
This is most likely due to some sort of outage.

`PinboardException_TooManyRequests` is thrown if the server responds with HTTP code 429, "Too Many Requests".
This means that you should slow down and wait a few minutes before making additional calls to `get()` or `get_all()`.

`PinboardException_InvalidResponse` is thrown if the server responds with any HTTP code other than 200 and 429, or if it returns invalid XML.

`PinboardException` is thrown in all other error situations,
such as attempting to save a bookmark with an invalid URL or an empty title.

Note that some methods will return `false` instead of throwing an exception on failure.
This is because the author has judged that errors in those cases are not "exceptional".
(For example, it is harmless to try to delete a bookmark that has already been deleted.)
All of cases are all documented above.
