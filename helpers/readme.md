# Helpers

This folder provides some helpers to get people started using mongoglue.

None of these files are required but can be useful.

## Crypt

Provides basic encryption and hashing functions for passwords and other senstive data. Please note that you should never encrypt a password but rather hash it.

The hashing function within this file uses bcrypt and the encryption function uses AES 256.

## GridView

Provides basic grid view abilities for tabular data in MongoDB for use with mongoglue.

## html

The HTML helper provides access to basic form and other HTML creation within the mongoglue ORM. It is merely designed to be a base object and it is strongly recommended
that you either extend it or not use it.

## MongoSession

Provides basic database session storage functionality with mongoglue

## Session

Provides basic session handling with mongoglue