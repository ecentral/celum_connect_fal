:navigation-title: FAQ

..  _faq:

================================
Frequently Asked Questions (FAQ)
================================

..  _faq-installation:

How can I install this extension?
==================================

See chapter :ref:`installation`.

..  _faq-cache-button:

How do I activate the CELUM cache-clear button?
================================================

Add ``options.clearCache.celum = 1`` to the TypoScript configuration
under the user or group settings. See chapter :ref:`usage`.

..  _api-key:

How do I find my API Key?
==========================

The API key is generated in the CELUM Configuration Management Application (CMA):

1. Log in to your CELUM backend (e.g. ``https://your-instance.celum.cloud``)
2. Navigate to **Advanced UI** (older user interface)
3. Go to **Administration > Configuration Management** and log in there
4. Under the **Authentication** category, click **Profiles**, then click **+ ADD** to create a new authentication profile
5. Select **Rest API** — a dialog will open where you can retrieve the API key

..  tip::

    Create a dedicated API user for TYPO3 to control access rights precisely.

..  _root-node-ids:

How do I find my Root Node IDs?
================================

The root node ID is the numerical ID of the CELUM folder that will serve as the entry point in the TYPO3 file selection:

1. In CELUM Content, navigate to the folder you want to use as the root — the ID is visible in the URL (e.g. ``1234_node``)
2. Open the folder's **Detail View** — the ID is visible in the upper left corner.

Multiple root node IDs can be entered as a comma-separated list — all configured nodes and their sub-nodes will be available recursively in TYPO3.

..  _faq-help:

Where to get help?
==================

See chapter :ref:`help`.
