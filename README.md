# CELUM Connect FAL

A TYPO3 FAL (File Abstraction Layer) driver that exposes assets and collections from a [CELUM](https://www.celum.com/) DAM system as a read-only TYPO3 file storage. Uses the CELUM REST API to fetch and cache asset metadata, preview URLs, and download links.

- **Extension key:** `celum_connect_fal`
- **Package:** `ecentral/celum-connect-fal`
- **Driver identifier:** `BrixCelumDriver`
- **TYPO3 compatibility:** 12.4 LTS, 13.4 LTS

## Requirements

- TYPO3 CMS 12.4 LTS or 13.4 LTS
- PHP 8.1 or higher
- A running CELUM DAM instance with REST API access

## Installation

```bash
composer require ecentral/celum-connect-fal
```

Then activate the extension via the TYPO3 backend under **Admin Tools > Extensions**, or via CLI:

```bash
./vendor/bin/typo3 extension:activate celum_connect_fal
```

## Configuration

The driver is configured per FAL storage in the TYPO3 backend under **File > Filelist > [Storage] > Edit**. Select **Driver: BrixCelumDriver** and fill in the FlexForm fields:

| Field | Type | Required | Default | Description                                                                                                                                   |
|---|---|---|---|-----------------------------------------------------------------------------------------------------------------------------------------------|
| `licenseKey` | string | yes | — | License key issued for your CELUM instance. It also determines the base URL of the CELUM REST API                                              |
| `roots` | string | yes | — | Comma-separated list of CELUM collection IDs to expose as root folders, e.g. `12,34,56` (see [Finding Root Node IDs](#finding-root-node-ids)) |
| `locale` | `en` \| `de` | no | `en` | Language used for asset names returned by the API                                                                                             |
| `defaultLocale` | `en` \| `de` | no | `en` | Fallback language when the primary locale is unavailable                                                                                      |
| `cacheLifetimeInMinutes` | integer (1–29) | no | `29` | How long API responses are cached in the TYPO3 cache framework                                                                                |

### Finding your API Key

The API key is generated in the CELUM Configuration Management Application (CMA):

1. Log in to your CELUM backend (e.g. `https://your-instance.celum.cloud`)
2. Navigate to **Advanced UI** (older user interface)
3. Go to **Administration > Configuration Management** and log in there
4. Under the **Authentication** category, click **Profiles**, then click **+ ADD** to create a new authentication profile
5. Select **Rest API** — a dialog will open where you can retrieve the API key

> **Tip:** Create a dedicated API user for TYPO3 to control access rights precisely.

### Finding Root Node IDs

The root node ID is the numerical ID of the CELUM folder that will serve as the entry point in the TYPO3 file selection:

1. In CELUM Content, navigate to the folder you want to use as the root - the ID is visible in the URL  (e.g. `1234_node`)
2. Open the folder's **Detail View** — the ID is visible in the upper left corner.

Multiple root node IDs can be entered as a comma-separated list — all configured nodes and their sub-nodes will be available recursively in TYPO3.

## Usage

### Creating a FAL Storage

1. Go to **Web > List  > Page Pid=0**.
2. Create a new file storage record.
3. Set **Driver** to **BrixCelumDriver**.
4. Fill in the connection settings under the **Configuration** tab (see [Configuration](#configuration)).
5. Save. TYPO3 will connect to the CELUM REST API and expose the configured root collections as folders.

### Read-Only Storage

The CELUM driver is **read-only**. All write operations (upload, rename, move, delete) will throw an exception. Assets must be managed inside CELUM and will be reflected automatically after the cache TTL expires.

### Clearing the Cache

A dedicated cache-clear button is available in the TYPO3 backend toolbar. To activate it, add the following TS-Config under the user or group settings:

```
options.clearCache.celum = 1
```

This clears all cached CELUM API responses immediately.
