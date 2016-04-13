#DEPRECATION NOTICE
This project has been **deprecated** in favor of [**JSON Loader**](https://github.com/wplib/json-loader).

#Typed Config
###A Library for Loading and using JSON in PHP Typed Objects

JSON has become one of the best data formats to chose because of it's simplicity and ubiquitous support among programming languages. Most new HTTP-based web services are returning JSON and it also makes a great configuration file format. What's more, it's really easy to load into a PHP object so it's perfect, right?

Well `json_decode()` works great, but it instantiates method-less `stdClass` objects and provide no method for establishing default values. Another downside of JSON is it does not support associative arrays, so working with JSON decoded data in PHP can feel somewhat _unnatural._ **But have no fear**, that's why _"Typed Config"_ is here.


The idea behind _Typed Config_ is to enable the PHP developer to model the expected JSON objects in PHP classes using data conventions in a special property to dictate the expected schema, and method-naming conventions to enable many different ways to initialize default values.

Because it makes an otherwise tedious programming problem easy we've found it refreshingly cool to work with. And once you've tried it we think you'll feel the same way too.

##More to Come...
