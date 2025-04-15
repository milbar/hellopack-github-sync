# 🧩 HelloPack GitHub Sync

A CLI-based GitHub sync tool that turns your private HelloPack WordPress plugins into GitHub repositories that are
tagged with the versions.

## 🔍 What does it do?

This tool:

- Authenticates with the **HelloPack API**
- Loads selected plugins from `config.json`
- Syncs them to private **GitHub repositories**
- Creates a minimal `composer.json` file
- Pushes the plugin contents with proper **version tags**
- Cleans up everything afterward

> Designed for teams who use HelloPack plugins and need a reliable way to pull them into Composer-managed environments.

---

## File Structure

```
hellopack-github-sync/
├── plugins/
├── src/
│   ├── GitHubService.php
│   ├── HelloApiService.php
│   ├── Helpers.php
│   └── PluginDeployer.php
├── vendor/
├── .env
├── .gitignore
├── composer.json
├── composer.lock
├── config.json
├── plugin-list.json
├── readme.md
└── run.php
```

## Purpose of Each File

### 1. **`plugins/`**

This folder is for storing plugins that will be synced from HelloPack or deployed to GitHub. If empty, plugins might be
dynamically fetched or managed by the application, rather than stored directly in the repository.

### 2. **`src/`** (Source Code Directory)

This folder contains the core PHP code for the project, which handles the functionality of syncing HelloPack plugins
with GitHub.

- **`GitHubService.php`**
  Manages interactions with GitHub’s API. It handles tasks such as creating, updating, or managing GitHub repositories,
  handling commits, releases, and possibly tags associated with the plugins from HelloPack.

- **`HelloApiService.php`**
  Responsible for interacting with the HelloPack API. This file includes the `getAllPlugins()` method to fetch plugin
  data from HelloPack. It also manages authentication and other HelloPack-specific interactions.

- **`Helpers.php`**
  Contains utility functions that assist the core logic of the project. The `slugify` function, for example, is used to
  convert plugin names into a standardized format (e.g., slugs).

- **`PluginDeployer.php`**
  Handles the deployment of plugins. It coordinates the process of deploying HelloPack plugins to GitHub repositories,
  utilizing both the `GitHubService` and `HelloApiService`. It manages the logic around how plugins are added or updated
  in the GitHub repositories.

### 3. **`.env`**

Stores sensitive configuration data, such as API tokens, GitHub credentials, and HelloPack endpoints. This file is used
to manage environment-specific variables that shouldn’t be hardcoded in the application.

### 4. **`config.json`**

Stores the list of plugins that are either being tracked or synced, along with their metadata and states (
enabled/disabled). This is used for managing the plugins in sync with GitHub.

### 5. **`plugin-list.json`**

The plugin-list.json file is a helper used to store the slugs of plugins fetched from the HelloPack API. It simplifies
managing plugin names within the script by converting them into URL-friendly slugs. This file is updated each time the
script runs if the conditions are matched `PLUGIN_CONFIG_AGE_THRESHOLD`. **This is not a configuration file**. The slugs
may not match the original folder or zip names of the plugins, as they are generated from the plugin names.

### 6. **`run.php`**

The main entry point for running the plugin syncing process. It orchestrates the fetching of plugins from HelloPack,
checks if the plugin list is up to date, and triggers the deployment of plugins to GitHub. This file is where the
syncing logic is executed, using the services defined in `src/`.

---

### Summary

- The **`src/`** folder contains the core logic for syncing HelloPack plugins to GitHub. It includes services for
  interacting with both HelloPack (`HelloApiService.php`) and GitHub (`GitHubService.php`), along with utility
  functions (`Helpers.php`) and deployment functionality (`PluginDeployer.php`).
- **`vendor/`** holds third-party libraries and configuration files for managing environment variables and ignoring
  files from Git.
- **Configuration and state management** of the plugins is handled in **`config.json`** and **`plugin-list.json`**.
- **`composer.json`** and **`composer.lock`** manage project dependencies.
- The **`run.php`** script is the main executable that likely runs the sync process.

## 🚀 Usage

### 1. Clone the repository

```bash
git clone git@github.com:milbar/hellopack-composer-mirror.git
cd hellopack-composer-mirror
composer install
```

### 2. Create a .env file

```dotenv
HELLO_API_TOKEN="your_hellopack_api_token" # An API key from the HelloConsole
HELLOPACK_USER="https://yourdomain.hu" # The domain for the api key
HELLOPACK_API_URL="https://api.v2.wp-json.app/v2" # Hello API url

GITHUB_TOKEN="your_github_token"
GITHUB_ORGANIZATION="your-org" # github org or user
GITHUB_REPO_PREFIX="hellopack" # The prefix for the repo if needed
PLUGIN_CONFIG_AGE_THRESHOLD="86400" # The plugin list update period in secounds
```

### Create a config file

Create a `config.json` in your root directory like the example below.

```json
{
  "plugins": {
    "plugin-sanitized-name": true,
    ...
  }
}
```

# Getting a GitHub API Token

To interact with the GitHub API, you will need to generate a personal access token. This token is used for
authenticating requests to GitHub's API and is required for performing actions such as creating repositories, pushing
code, or interacting with other resources in your GitHub account.

### Steps to Generate a GitHub Personal Access Token

1. **Sign In to GitHub**:
    - Go to [GitHub](https://github.com/) and sign in with your account.

2. **Navigate to Settings**:
    - In the top right corner of the GitHub page, click on your profile picture.
    - From the dropdown, select **Settings**.

3. **Go to Developer Settings**:
    - On the left sidebar of the Settings page, scroll down and click on **Developer settings**.

4. **Access Personal Access Tokens**:
    - Under **Developer settings**, click on **Personal access tokens**.
    - You will be redirected to the page where you can create and manage your personal access tokens.
    - For now use the **Personal access tokens (classic)** option

5. **Generate a New Token**:
    - Click on the **Generate new token** button.

6. **Set Scopes and Permissions**:
    - Choose the appropriate scopes for your token. The scopes define what the token can do.
        - For syncing or deploying plugins, you might need to grant the following permissions:
            - **repo** (Full control of private repositories)
            - **workflow** (Access to GitHub Actions workflows, if applicable)
        - Select the necessary scopes based on your requirements.

7. **Generate Token**:
    - After selecting the required scopes, click on the **Generate token** button at the bottom of the page.
    - **Important**: Make sure to copy the token immediately after it’s generated, as you will not be able to see it
      again. Store it securely.

8. **Use the Token**:
    - Now you can use the generated personal access token to authenticate with the GitHub API.
    - In your application, use this token in HTTP headers to authenticate requests:
      ```bash
      Authorization: Bearer YOUR_PERSONAL_ACCESS_TOKEN
      ```

### Storing Your Token

For security reasons, **never expose your personal access token** in public repositories or in the code directly. It’s
best to store it in environment variables or use a configuration file that is not included in version control.

You can store the token in a `.env` file (e.g., `GITHUB_TOKEN=your_token_here`) and access it in your application code.

---

### Revoking or Regenerating Your Token

If your token is compromised or no longer needed, you can revoke or regenerate it by going back to the **Personal access
tokens** page in GitHub's Developer settings.

1. Go to the **Personal access tokens** page.
2. Find the token you want to delete.
3. Click on the **Delete** button next to the token or **Regenerate** it if you need to update the token.

---

By following these steps, you'll be able to generate and use a GitHub API token to interact with GitHub’s services
securely and efficiently.

# Disclaimer

This project has been created specifically for my own use and may not be suitable for every use case. While I am open to
suggestions and improvements, please be aware that **you use this at your own risk**.

### Key Points:

- **Not Production-Ready**: This is a personal project that may not have been thoroughly tested in all environments.
- **Security**: Ensure that you properly secure any sensitive data, such as API tokens, in your local environment.
- **Contributions Welcome**: If you have suggestions for improvements or bug fixes, feel free to open an issue or submit
  a pull request. I am open to making the project better, but I cannot guarantee that all suggestions will be
  implemented immediately.

By using or contributing to this project, you acknowledge that you are doing so with an understanding of its current
state and limitations.
