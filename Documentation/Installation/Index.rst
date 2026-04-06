..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

-  TYPO3 CMS 12.4 LTS or 13.4 LTS
-  PHP 8.1 or higher
-  A running CELUM DAM instance with REST API access

..  _installation-composer:

Installation via Composer
=========================

Run the following command in your TYPO3 project root:

..  code-block:: bash

    composer require brix/celum-connect-fal

Then activate the extension in the TYPO3 backend under
:guilabel:`Admin Tools > Extensions`, or via the CLI:

..  code-block:: bash

    ./vendor/bin/typo3 extension:activate celum_connect_fal

See also `Installing extensions, TYPO3 Getting started
<https://docs.typo3.org/permalink/t3start:installing-extensions>`_.
