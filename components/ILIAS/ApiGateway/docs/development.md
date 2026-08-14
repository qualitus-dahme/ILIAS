# Testing During Development

This guide explains how to set up the **ApiGateway component** in an ILIAS development environment for testing purposes, especially before it is part of an official release.

Since the component is still under active development, it may require manual steps to integrate it into an existing ILIAS instance. This involves obtaining the component files, applying some necessary changes to the ILIAS core, and running the setup process.

### 1. Prerequisites

- A running ILIAS instance for development.
- Shell access to the ILIAS root directory.
- Correct file permissions that allow the web server user (e.g., `www-data`) to read the component files and for you to run commands.

### 2. Installation and Setup

The following steps will guide you through the manual installation process.

#### Step 1: Obtain the Component

First, you need to get the component source code. You can do this by downloading it directly from the development branch.

- **Download:** [ApiGateway Development Branch (zipped)](https://github.com/GitHamo/ILIAS/archive/refs/heads/feature/apigateway-component.zip)

After downloading, extract the archive. You will find the `ApiGateway` component inside the `components/ILIAS/` directory of the extracted files.

#### Step 2: Copy Component and Apply Core Changes

Next, copy the component into your ILIAS instance and apply a patch that includes required changes for the ILIAS core.

> **Note:** The following commands should be executed from your ILIAS root directory. You may need to adjust them based on your system's user and permission setup. It's often necessary to run commands as the web server's user.

1. **Copy the component directory:**

    ```bash
    # Replace <PATH_TO_EXTRACTED_FILES> with the actual path
    cp -r <PATH_TO_EXTRACTED_FILES>/components/ILIAS/ApiGateway ./components/ILIAS/
    ```

2. **Apply the core changes patch:** This patch makes necessary modifications to the ILIAS core to make the ApiGateway component work correctly.

    ```bash
    # This applies the patch file included with the component
    git apply components/ILIAS/ApiGateway/dev-testing.patch
    ```

    *Note: This patch ([`dev-testing.patch`](../dev-testing.patch)) is temporary and will not be needed once the ApiGateway component is officially integrated into the ILIAS codebase. If `git apply` reports errors, your ILIAS core version might be incompatible with the patch. Ensure you are using a compatible ILIAS version.*

    *Important Note on `.gitignore`*: This patch also includes an entry to add the `ApiGateway` component to your local `.gitignore` file. This prevents the component files from cluttering your ILIAS development environment's Git status, especially if you plan to manage the component in a separate Git fork. If you intend to commit the component directly into your main ILIAS repository, you might need to revert this `.gitignore` change.

3. **Set file permissions:** The web server needs to be able to read the component's files.

    ```bash
    # Example for Debian/Ubuntu based systems. Adapt if needed.
    sudo chown www-data:www-data -R components/ILIAS/ApiGateway
    ```

#### Step 3: Install Composer Dependencies

Now, run Composer to install the PHP dependencies required by the component.

```bash
composer install --no-dev
```

When Composer runs, it should detect and read the new component. Look for the following output to confirm:

```text
Reading Vendors from "./components"...
    Reading Components from Vendor "ILIAS"...
        Reading Component ...
        ...
        Reading Component "ILIAS\ApiGateway"...
        Reading Component ...
        ...
```

#### Step 4: Run the Database Update

Finally, run the ILIAS setup script to apply the database migrations for the component.

```bash
php cli/setup.php update --yes
```

You should see output confirming that the component's setup steps were executed:

```text
Add new admin node to tree (type=apig;title=ApiGateway)...                 [OK]
Database update steps in ILIAS\ApiGateway\Setup\Steps\ApiGatewayDBUpdateSteps....[OK]
```

### 3. Verification

Once the setup is complete, you should verify that the API is functional by following the instructions in the [**Getting Started**](../README.md#getting-started) section of the main `README.md` file.

This typically involves enabling the API in the administration panel and sending a test request to the `/ping` endpoint.
