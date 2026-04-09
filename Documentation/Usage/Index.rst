..  _usage:

=====
Usage
=====

..  _usage-create-storage:

Creating a FAL Storage
======================

#. Go to :guilabel:`List` (page PID=0).
#. Create a new :guilabel:`File Storage` record.
#. Set :guilabel:`Driver` to :guilabel:`BrixCelumDriver`.
#. Fill in the connection settings under the :guilabel:`Configuration` tab
   (see :ref:`configuration`).
#. Save. TYPO3 will connect to the CELUM REST API and expose the configured
   root collections as folders.

..  _usage-read-only:

Read-Only Storage
=================

The CELUM driver is **read-only**. All write operations (upload, rename,
move, delete) will throw an exception. Assets must be managed inside CELUM
and will be reflected automatically after the cache TTL expires.

..  _usage-clear-cache:

Clearing the Cache
==================

A dedicated cache-clear button is available in the TYPO3 backend toolbar.
To activate it, add the following TypoScript configuration under the user
or group settings:

..  code-block:: typoscript

    options.clearCache.celum = 1

This clears all cached CELUM API responses immediately.
