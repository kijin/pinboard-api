
Pinboard API Client in PHP
==========================

This library implements a client for the [Pinboard API](https://pinboard.in/api/).
All of the XML juggling is abstracted away, so that you can work with native PHP arrays and objects.
Currently, all features of API v1 are supported.

This library requires PHP 5 with the cURL extension. SSL support must be enabled.
This library also requires SimpleXML, which is enabled by default in most PHP 5 installations.

This library is released under the [MIT License](http://opensource.org/licenses/MIT).
The author and contributor(s) are not affiliated with Pinboard in any way except as customers.


### Getting Started

Installation (without composer):

    include 'pinboard-api.php';

Installation (with composer):

    "require": {
        "kijin/pinboard-api": "dev-master"
    }

Bootstrap:

    $pinboard = new PinboardAPI('username', 'password_or_token');

Create a new bookmark:

    $bookmark = new PinboardBookmark;
    $bookmark->url = 'https://pinboard.in/';
    $bookmark->title = 'Pinboard';
    $bookmark->description = 'An awesome bookmarking service';
    $bookmark->tags = array('awesome', 'bookmarking');
    $bookmark->save();
    
Find and edit an existing bookmark:

    $bookmarks = $pinboard->search_by_url('https://delicious.com/');
    if (count($bookmarks)) {
        $bookmark = $bookmark[0];
        $bookmark->description = 'Not so tasty anymore';
        $bookmark->tags[] = 'not-awesome'
        $bookmark->save();
    }

Delete a bookmark:

    $bookmark->delete();

Get a list of your tags:

    $tags = $pinboard->get_tags();
    foreach ($tags as $tag) {
        echo "Tag '{$tag}' has {$tag->count} bookmarks.\n";
    }


Classes and Methods
===================

The Pinboard API Client is liberal in what it accepts but conservative in what it produces.
Timestamps and tags are accepted in various formats,
and a full `PinboardBookmark` object can often be replaced with just a URL.
However, timestamps returned by the API Client will always be Unix timestamps
(except in the case of `get_dates()` where you'll get dates in the `YYYY-MM-DD` format),
and tags will always be strings in an array.


PinboardAPI->__construct()
--------------------------

Arguments:

  - _required_ **$user** : your Pinboard username.
  - _required_ **$pass** : your Pinboard password or API token.
  - _optional_ **$connection_timeout** : connection timeout in seconds. Default is 10.
  - _optional_ **$request_timeout** : request timeout in seconds. Default is 30.

If you want to use your API token instead of your account password,
you must use the full string as it appears in the "settings" page.
This includes your username, a colon character, and 20 uppercase hexademical digits.

For example:

    $pinboard = new PinboardAPI('user', 'user:0123456789ABCDEFABCD');

You can also pass `null` instead of your username, since the token already includes your username.
However, using any value other than your own username or `null` will result in authentication failure.

Password authentication will continue to work normally until Pinboard stops supporting it.


PinboardAPI->enable_logging()
-----------------------------

Use this method if you would like to get notified every time the API Client makes a remote request.
This can be useful for debugging.

Arguments:

  - _required_ **$func** : a callable that takes one argument.

The callable can be either a function name, a method name, or a closure.
It will be passed the remote URL whenever the API Client makes a request to Pinboard.

The following example will print `https://api.pinboard.in/v1/posts/update` to the console.

    $pinboard = new PinboardAPI('username', 'password');
    $pinboard->enable_logging(function($str) { echo "$str\n"; });
    $updated_time = $pinboard->get_updated_time();

PinboardAPI->get_updated_time()
-------------------------------

Use this method to find out when you last made changes to your bookmarks.
This can help reduce unnecessary calls to expensive methods such as `get_all()`.

API Method Call: `posts/update`

Arguments: none.

Returns: integer (Unix timestamp).


PinboardAPI->get_recent()
-------------------------

Use this method to grab your most recent bookmarks, optionally filtered by up to three tags.
Note that Pinboard may impose rate limiting on this method.

API Method Call: `posts/recent`

Arguments:

  - _optional_ **$count** : integer, how many bookmarks to return. Default is 15. Maximum is 100.
  - _optional_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.

Returns: an array of `PinboardBookmark` objects.


PinboardAPI->get_all()
----------------------

Use this method to grab all of your bookmarks, optionally filtered by up to three tags or a time interval.
Note that Pinboard may impose rate limiting on this method.

If you would like to skip any arguments, use `null` in place of the missing argument.

API Method Call: `posts/all`

Arguments:

  - _optional_ **$count** : integer, how many bookmarks to return. Default is all.
  - _optional_ **$offset** : integer, when used with `$count`, how many bookmarks to skip.
  - _optional_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.
  - _optional_ **$from** : a Unix timestamp, or any string that PHP can parse into a timestamp.
  - _optional_ **$to** : a Unix timestamp, or any string that PHP can parse into a timestamp.

Returns: an array of `PinboardBookmark` objects.


PinboardAPI->get()
------------------

Use this method to grab some of your bookmarks, optionally filtered by up to three tags.
Please read Pinboard's [documentation](https://pinboard.in/api/#posts_get)
for details on how this method behaves when the date argument is absent.

If you would like to skip any arguments, use `null` in place of the missing argument.

API Method Call: `posts/get`

Arguments:

  - _optional_ **$url** : the exact URL of the bookmark to look for.
  - _optional_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.
  - _optional_ **$date** : a Unix timestamp, any string that PHP can parse into a timestamp, or a date in the format `YYYY-MM-DD`.

Returns: an array of `PinboardBookmark` objects.


PinboardAPI->search_by_url()
----------------------------

A shortcut to `get()`.
Note that this method will return an array even if there is only one bookmark.

Arguments:

  - _required_ **$url** : the exact URL of the bookmark to look for.

Returns: an array of `PinboardBookmark` objects.


PinboardAPI->search_by_tag()
----------------------------

A shortcut to `get_all()`.

Arguments:

  - _required_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.

Returns: an array of `PinboardBookmark` objects.


PinboardAPI->search_by_date()
-----------------------------

A shortcut to `get()`.

Arguments:

  - _required_ **$date** : a Unix timestamp, any string that PHP can parse into a timestamp, or a date in the format `YYYY-MM-DD`.

Returns: an array of `PinboardBookmark` objects.


PinboardAPI->search_by_interval()
---------------------------------

A shortcut to `get_all()`.

Arguments:

  - _required_ **$from** : a Unix timestamp, or any string that PHP can parse into a timestamp.
  - _required_ **$to** : a Unix timestamp, or any string that PHP can parse into a timestamp.

Returns: an array of `PinboardBookmark` objects.


PinboardAPI->save()
-------------------

Use this method to add a new bookmark or edit an existing bookmark.

API Method Call: `posts/add`

Arguments:

  - _required_ **$bookmark** : a `PinboardBookmark` object to save.
  - _optional_ **$replace** : set to `false` if you don't want to overwrite an existing bookmark with the same URL. Default is `true`.

Returns: `true` on success and `false` on failure. Call `get_last_status()` to read the error message in case of a failure.

When not using `$replace`, it may be more intuitive to use `PinboardBookmark->save()` instead. See below for more information on using this alternative syntax.


PinboardAPI->delete()
---------------------

Use this method to delete a bookmark.

API Method Call: `posts/delete`

Arguments:

  - _required_ **$bookmark** : a `PinboardBookmark` object, or a URL.

Returns: `true` on success and `false` on failure. Call `get_last_status()` to read the error message in case of a failure.

Note that it may be more intuitive to use `PinboardBookmark->delete()` instead. See below for more information on using this alternative syntax.


PinboardAPI->get_dates()
------------------------

Use this method to get a list of dates on which you added bookmarks, with the number of bookmarks for each day.
Optionally filtered by up to three tags.

API Method Call: `posts/dates`

Arguments:

  - _optional_ **$tags** : an array of 1-3 tags, or a string with spaces between tags.

Returns: an array of `PinboardDate` objects. (These objects behave like strings.)


PinboardAPI->get_suggested_tags()
---------------------------------

Use this method to get tag suggestions for a bookmark or URL.

API Method Call: `posts/suggest`

Arguments:

  - _required_ **$bookmark** : a `PinboardBookmark` object, or a URL.

Returns: an associative array with two keys, `popular` and `recommended`, each of which contains an array of strings.


PinboardAPI->get_tags()
-----------------------

Use this method to get a list of all your tags, with the number of bookmarks for each tag.

API Method Call: `tags/get`

Arguments: none.

Returns: an array of `PinboardTag` objects. (These objects behave like strings.)


PinboardAPI->rename_tag()
-------------------------

Use this method to rename one tag to another.

API Method Call: `tags/rename`

Arguments:

  - _required_ **$old** : the old name, as a string.
  - _required_ **$new** : the new name, as a string.

Returns: `true` on success and `false` on failure. Call `get_last_status()` to read the error message in case of a failure.


PinboardAPI->delete_tag()
-------------------------

Use this method to delete a tag. Bookmarks will not be deleted.

API Method Call: `tags/delete`

Arguments:

  - _required_ **$tag** : the tag to delete, as a string.

Returns: `true` on success and `false` on failure. Call `get_last_status()` to read the error message in case of a failure.


PinbiardAPI->list_notes()
-------------------------

Use this method to list your notes.

API Method Call: `notes/list`

Arguments: none.

Returns: an array of `PinboardNote` objects.

As of August 2014, this API call only returns metadata.
In order to get the content of each note, please use `get_note()`.


PinboardAPI->get_note()
-----------------------

Use this method to get the contents of a single note.

API Method Call: `notes/ID`

Arguments:

  - _required_ **$id** : the ID of the note that you want to get.

Returns: a `PinboardNote` object, or `false` if the note does not exist.

As of August 2014, this API call returns the title, content, and minimal metadata.
In order to get the rest of the metadata, please use `list_notes()`.


PinboardAPI->get_rss_token()
----------------------------

Use this method to get your secret RSS token.

API Method Call: `user/secret`

Arguments: none.

Returns: a string containing your RSS token.


PinboardAPI->get_api_token()
----------------------------

Use this method to get your API token.

API Method Call: `user/api_token`

Arguments: none.

Returns: a string containing your API token.

Note that this method only returns the hexademical part of the API token.
In order to use the token for authentication, you must combine it with the username.
See the documentation for `__construct()` for more information on how to use the token for authentication.


PinboardAPI->get_last_status()
------------------------------

Use this method to retrieve any error message for one of the following methods: `save()`, `delete()`, `rename_tag()`, and `delete_tag()`.
If the last operation did not fail, this method will return "done".
If no applicable operation has been performed, this method will return `null`.

Arguments: none.

Returns: a string containing the last error message, or `null`.


PinboardAPI->dump()
-------------------

Use this method to back up all of your bookmarks.
The dump will be produced in an XML format that can be easily imported into Pinboard, Delicious,
or any other online bookmarking service that supports importing bookmarks in the Delicious format.
Note that Pinboard may impose rate limiting on this method.

API Method Call: `posts/all`

Arguments: none.

Returns: a string containing the XML dump.


The PinboardBookmark class
--------------------------

This class is used with `save()` and several other methods that take bookmarks as an argument.
Its use is required when calling `save()`, but in most other cases it can be substituted with just a URL.
The API Client will also return instances of this class whenever it fetches bookmarks from Pinboard.

The following properties can be adjusted freely:

  - _required_ **url** : the URL of the bookmark.
  - _required_ **title** : the title of the bookmark.
  - _optional_ **description** : any additional description.
  - _optional_ **timestamp** : a Unix timestamp, or any string that PHP can parse into a timestamp.
  - _optional_ **tags** : an array of 1-3 tags, or a string with spaces between tags.
  - _optional_ **is_public** : `true` or `false`. Default is determined by your Pinboard account settings.
  - _optional_ **is_unread** : `true` or `false`. Default is `false`.

The following properties are also public, but they will not be saved when you call `save()`:

  - **hash** : an MD5 hash of the URL that Pinboard uses to uniquely identify bookmarks.
  - **meta** : another hash that can be used to detect when a bookmark is changed.
  - **others** : the number of other Pinboard users who have bookmarked the same URL.

The following methods are available:

  - **save()** : save this bookmark.
  - **delete()** : delete this bookmark.

You can use these methods instead of `PinboardAPI->save($bookmark)` and `PinboardAPI->delete($bookmark)` to save or delete individual bookmarks.
This may be more intuitive to developers who are used to common ORM idioms, which this library tries to mimic.

For example, instead of:

    $pinboard->save($bookmark);

You can simply do:

    $bookmark->save();

If only one instance of `PinboardAPI` exists in the current script (which will usually be the case),
the API Client automatically uses it to save or delete all bookmarks, even newly created ones.
So there is no need for `PinboardBookmark` instances to interact explicitly with `PinboardAPI` instances.

However, if you create multiple instances of `PinboardAPI` using different login credentials
(perhaps because you want to copy or move bookmarks from one Pinboard account to another),
these methods will throw `PinboardException` because they don't know which instance to use.
In that case, you should use the equivalent methods on `PinboardAPI` instances instead,
or pass the appropriate `PinboardAPI` instance as an argument to `save()` and `delete()`.
Both methods take one optional argument, which should be a `PinboardAPI` instance.

The following example copies bookmarks from one Pinboard account to another:

    $pinboard1 = new PinboardAPI('user1', 'pass1');
    $pinboard2 = new PinboardAPI('user2', 'pass2');
    $bookmarks = $pinboard1->search_by_tag('tag');
    foreach ($bookmarks as $bookmark) {
        $bookmark->save($pinboard2);  // Equivalent to $pinboard2->save($bookmark);
        sleep(3);  // Comply with Pinboard's rate limiting policy
    }

Even when using multiple instances, bookmarks that were retrieved using one of the `get_*` or `search_*` methods
will remember which instance they came from, and therefore `save()` and `delete()` will work without any problem,
as shown in the following example:

    $pinboard1 = new PinboardAPI('user1', 'pass1');
    $pinboard2 = new PinboardAPI('user2', 'pass2');
    $bookmarks = $pinboard1->search_by_url('http://awesome-website.com/');
    $bookmark = $bookmarks[0];
    $bookmark->description = 'New description';
    $bookmark->save();  // Automatically saved to $pinboard1


The PinboardDate class
----------------------

Instances of this class are returned by `get_dates()`. They contain dates in the format `YYYY-MM-DD`.
For all intents and purposes, these objects can be used exactly like strings.
The only difference is that it has a `count` property, which contains the number of bookmarks added on the corresponding date.

Examples:

    echo $date;         // prints '2012-03-20'
    echo $date->date;   // prints '2012-03-20'
    echo $date->count;  // prints '16'


The PinboardTag class
---------------------

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


The PinboardNote class
---------------------

Instances of this class are returned by `list_notes()` and `get_note()`.
Currently, it is not possible to save a note to Pinboard through the API.

Because different API calls return a different subset of content and metadata, not all of the following properties may be populated.
The only property that is guaranteed to be populated is `id`. Unpopulated properties will have the value of `null`.
See documentation for the methods above for more details.

The following properties are available:

  - **id**
  - **title**
  - **hash**
  - **created_at**
  - **updated_at**
  - **length**
  - **text**


Error Handling
--------------

The Pinboard API Client defines the following exceptions:

  - `PinboardException` (extends `Exception`) is thrown in all error situations not covered by the other exceptions,
    such as attempting to save a bookmark with an invalid URL or an empty title.
  - `PinboardException_ConnectionError` (extends `PinboardException`) is thrown if the library encounters problems
    while connecting to Pinboard's servers. This is most likely due to some sort of outage.
  - `PinboardException_AuthenticationFailure` (extends `PinboardException`) is thrown in case of authentication failure.
    This may be because the username and password/token do not match, or because you supplied the API token in the wrong format.
    (The API token must include the username, not just the hexademical portion.)
  - `PinboardException_TooManyRequests` (extends `PinboardException`) is thrown if the server responds with HTTP code 429, "Too Many Requests".
    This means that you should slow down and wait a few minutes before making additional calls to `get()` or `get_all()`.
    See [here](https://pinboard.in/tos/) and [here](https://pinboard.in/api/#limits) for more information on rate limiting.
  - `PinboardException_InvalidResponse` (extends `PinboardException`) is thrown if the server responds
    with any HTTP code other than 200 and 429, or if it returns invalid XML.

Note that some methods will return `false` instead of throwing an exception on failure.
This is because the author has judged that errors in those cases are not "exceptional".
(For example, it is harmless to try to delete a bookmark that has already been deleted.)
All such cases are clearly documented above.
