# Moneris Payment Gateway for Craft Commerce

A Craft CMS plugin that provides a Moneris payment gateway for Craft Commerce, using the Moneris Gateway API PHP library.

## Requirements

- PHP 8.2 or later
- Craft CMS 5.1.0 or later
- Craft Commerce 5.0.13 or later

## Installation

1. Add the GitHub repository to your Craft project’s `composer.json` (required if the package is not available on Packagist):

   ```json
   {
       "repositories": [
           {
               "type": "vcs",
               "url": "git@github.com:allomambo/commerce-moneris.git"
           }
       ]
   }
   ```

2. Require the plugin:

   ```bash
   composer require allomambo/commerce-moneris
   ```

3. Install the plugin in Craft:
   - Go to **Settings** → **Plugins**
   - Find **Moneris for Craft Commerce**
   - Click **Install**

For developing against a local checkout of this plugin (Composer path repository, DDEV mounts, etc.), see [Local development](docs/local-development.md).

## Configuration

1. Go to **Commerce** → **Settings** → **Gateways**
2. Create a new gateway and choose **Moneris**
3. Configure:

   | Setting | Description |
   | --- | --- |
   | **Store ID** | Your Moneris Store ID |
   | **API Token** | Your Moneris API Token |
   | **Test Mode** | Defaults to **enabled** (test/staging). Must be set explicitly to disabled/`false` for production |
   | **Enable AVS** | Address Verification System |
   | **Enable CVD** | Card Verification Digit |

### Environment variables

Store ID, API Token, and Test Mode support Craft’s environmental settings. In the control panel, set each field to a `$VARIABLE_NAME` reference (autosuggest for text fields; boolean menu for Test Mode).

Example `.env`:

```dotenv
MONERIS_STORE_ID=store1
MONERIS_API_TOKEN=your-api-token
MONERIS_TEST_MODE=true
```

Then in the gateway settings:

- Store ID → `$MONERIS_STORE_ID`
- API Token → `$MONERIS_API_TOKEN`
- Test Mode → `$MONERIS_TEST_MODE`

**Test Mode** stays on unless the resolved value is explicitly false. Accepted boolean values: `true`, `false`, `1`, `0`, `yes`, `no`, `on`, `off`.

For production, set `MONERIS_TEST_MODE=false` (or choose **No** in the control panel).

## Features

- Purchase (direct payment)
- Authorize
- Capture
- Full and partial refunds
- AVS support
- CVD support

## License

Proprietary
