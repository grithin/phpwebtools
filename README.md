# Grithin's PHP Web Tools

## Curl
Basic curl class to handle the various curl function and avoid some common pitfalls of  php curl (ex: post encoding being larger than browser post encoding).

### Common Use

```php
use \Grithin\Curl;

$curl = new Curl;

# can set headers either directly using 'options' or through instance attributes
$curl->options['CURLOPT_USERAGENT'] = 'harmless autobot';
$curl->user_agent = 'harmless autobot';

# can provide GET parameters as an array or as a string
$response = $curl->get('http://google.com/?s=bob');
$response = $curl->get('http://google.com/',['s'=>'bob']);


$response = $curl->post('http://google.com/',['s'=>'bob']);
$response = $curl->post('http://google.com/','s=bob');
$response = $curl->post('http://google.com/',json_encode(['bob'=>'s']));
```

###  File upload

Uses the old style, but works well enough
```php
$response = $curl->post('http://thoughtpush.com/', ['s'=>'bob'], ['file1'=>'@'.__FILE__]);
```

### Response Object

The Curl-send methods return  a CurlReponse object with a `headers` array attribute and a  `body` string attribute.


```php

use \Grithin\Curl;

$curl = new Curl;
$response = $curl->get('http://thoughtpush.com');

\Grithin\Debug::out($response->headers);
/*
[base:index.php:14] 1: [
	'Http-Version' : '1.1'
	'Status-Code' : '200'
	'Status' : '200 OK'
	'Server' : 'nginx/1.4.6 (Ubuntu)'
	'Date' : 'Fri, 09 Oct 2015 19:36:01 GMT'
	...
*/
\Grithin\Debug::out($response->body);
/*
[base:index.php:15] 2: '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
	<head>
*/
```

### Debugging

```php
$curl->options[CURLOPT_VERBOSE] = true;
```

## DomTools

Methods used for better handling of DOM and xpath.

Read the inline code comments

```php
list($dom, $xpath) = DomTools::loadHtml($response->body);
```


## WebData

Various web data methods including ones used to fulfill ASP validations by parsing responses and setting post parameters.

Read the inline code comments