php-tika
========

Drive Apache tika from PHP

This still has much work before it can be declared to be something
useable in other projects.

At the moment it just looks at running tika from the command-line.
Taking tika server under its wing would come next. Running tika
through a php-java bridge would be awesome.

At the moment, every way of accessing tika has a different interface,
with different capabilities. I would like to hide all that in this
PHP library, but it is difficult to see where the lowest-common
denominator sits wrt features.

