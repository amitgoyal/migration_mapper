This Module Expects you to create your own custom module to place your
Exported mapped Scripts in
To do this the easy way use drupal Console to "drupal grm"
or
Create a new Dir in our /modules/custom with the following
mymodule/
mymodule.info.yml
config/install/
in the config/install/ this is where place your exported migrations


Note: This is by no way a perfect solution you still may be required to manipulate
your exported mapped migration to get exactly what you want.

WARNING: Never use this on a live site before testing locally!!!

We suggest use https://www.mockaroo.com/ to make some sample data and have a play
with some test "custom" migrations before using your own data.

Please remove this module from production Sites.