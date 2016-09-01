[![Build Status](https://travis-ci.org/aligent/CacheObserver.svg)](https://travis-ci.org/laravel/framework)
[![Total Downloads](https://poser.pugx.org/laravel/framework/d/total.svg)](https://packagist.org/packages/laravel/framework)

Aligent CacheObserver Extension
===============================
Magento extension to add cache keys and tags to blocks that are not cached by default.

Facts
-----
- version: 2.0.0
- extension key: Aligent_CacheObserver
- [extension on GitHub](https://github.com/aligent/CacheObserver)

Description
-----------
Magento extension to add cache keys and tags to blocks that are not cached by default.

Usage
-----
Add custom cache observer handlers using the following in your module's config.xml.
See CacheObserver's config.xml for further details.
Note: If you may use the CacheObserver's model/methods for caching your own blocks if required.
```
<config>
    <cacheObserver>
        <!--
            The observer id should be a unique key. Note that all observers will be sorted alphabetically by the
            observer key.
            You may make use of Magento's config.xml merging to overwrite another module's configs if needed.
        -->
        <module_name_observer_id>
            <!--
                `model` is a standard model alias to tell the model
            -->
            <model>module_name/model</model>
            <method>myCustomCacheObserverMethod</method>
            <classes>
                <!-- A list of classes to
                <Some_Module_Block_To_Cache/>
            </classes>
        </module_name_observ_id>
    </cacheObserver>
</config>
```

Requirements
------------
- PHP >= 5.2.0
- Mage_Core
- ...

Compatibility
-------------
- Magento >= 1.4

Installation Instructions
-------------------------
1. Install version ~2.0.0 of the extension via composer.

Uninstallation
--------------
1. Remove the extension via composer.

Support
-------
If you have any issues with this extension, open an issue on [GitHub](https://github.com/aligent/CacheObserver/issues).

Developer
---------
Luke Mills
[http://www.aligent.com.au](http://www.aligent.com.au)

Licence
-------
OSL-3.0

Copyright
---------
(c) 2015 Aligent
