const https = require('https');

// Configuration - these should be set via environment variables
const GITHUB_API_URL = 'api.github.com';
const GITHUB_TOKEN = process.env.GITHUB_TOKEN;
const GITHUB_OWNER = process.env.GITHUB_OWNER || 'Adyen';
const GITHUB_REPO = process.env.GITHUB_REPO || 'adyen-magento2';
const RELEASE_TAG = process.env.RELEASE_TAG;

// Adobe EQP API credentials
const ADOBE_EQP_APP_ID = process.env.ADOBE_EQP_APP_ID;
const ADOBE_EQP_APP_SECRET = process.env.ADOBE_EQP_APP_SECRET;
const ADOBE_EQP_API_URL = 'commercedeveloper.adobe.com';

/**
 * Makes an HTTPS request and returns a promise
 * @param {Object} options - Request options
 * @param {string|null} postData - Data to send in POST request
 * @returns {Promise<Object>} - Response data
 */
function makeRequest(options, postData = null) {
    return new Promise((resolve, reject) => {
        const req = https.request(options, (res) => {
            let data = '';

            res.on('data', (chunk) => {
                data += chunk;
            });

            res.on('end', () => {
                try {
                    const jsonData = JSON.parse(data);
                    if (res.statusCode >= 200 && res.statusCode < 300) {
                        resolve(jsonData);
                    } else {
                        reject(new Error(`HTTP ${res.statusCode}: ${JSON.stringify(jsonData)}`));
                    }
                } catch (e) {
                    reject(new Error(`Failed to parse response: ${data}`));
                }
            });
        });

        req.on('error', (e) => {
            reject(e);
        });

        if (postData) {
            req.write(postData);
        }

        req.end();
    });
}

/**
 * Gets release information from GitHub API for a given tag
 *
 * @param {string} repository - Repository
 * @param {string} tag - Release tag
 *
 * @returns {Promise<Object>} - Release information
 */
async function getGithubRelease(repository, tag) {
    if (!GITHUB_TOKEN) {
        throw new Error('GITHUB_TOKEN is required!');
    }

    const options = {
        hostname: GITHUB_API_URL,
        path: `/repos/${repository}/releases/tags/${tag}`,
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${GITHUB_TOKEN}`
        }
    };

    return makeRequest(options);
}

/**
 * Gets access token from Adobe EQP API
 *
 * @param {string} appId - Adobe EQP Application ID
 * @param {string} appSecret - Adobe EQP Application Secret
 *
 * @returns {Promise<Object>} - Access token response
 */
async function getAdobeEQPAccessToken(appId, appSecret) {
    const credentials = Buffer.from(`${appId}:${appSecret}`).toString('base64');

    const options = {
        hostname: ADOBE_EQP_API_URL,
        path: '/rest/v1/app/session/token',
        method: 'POST',
        headers: {
            'Authorization': `Basic ${credentials}`,
            'Content-Type': 'application/json'
        }
    };

    console.log('Requesting Adobe EQP access token...');
    return makeRequest(options);
}

/**
 * Main function to orchestrate the release process
 */
async function main() {
    // Validate required environment variables
    if (!RELEASE_TAG) {
        console.error('Error: RELEASE_TAG environment variable is required');
        process.exit(1);
    }

    if (!ADOBE_EQP_APP_ID || !ADOBE_EQP_APP_SECRET) {
        console.error('Error: ADOBE_EQP_APP_ID and ADOBE_EQP_APP_SECRET environment variables are required');
        process.exit(1);
    }

    let releaseNotes = '';

    try {
        // Step 1: Get GitHub release information
        const releaseInfo = await getGithubRelease(GITHUB_OWNER, GITHUB_REPO, RELEASE_TAG);
        console.log('Successfully fetched release information');
        console.log(`Release Name: ${releaseInfo.name}`);
        console.log(`Published At: ${releaseInfo.published_at}`);

        // Step 2: Extract and store release notes
        releaseNotes = releaseInfo.body || '';
        console.log('\n--- Release Notes ---');
        console.log(releaseNotes);
        console.log('--- End Release Notes ---\n');

        // Step 3: Get Adobe EQP access token
        const tokenResponse = await getAdobeEQPAccessToken(ADOBE_EQP_APP_ID, ADOBE_EQP_APP_SECRET);
        console.log('Successfully obtained Adobe EQP access token');

        // Step 4: Download the release zipball
        const zipballPath = path.join(process.cwd(), `${RELEASE_TAG}.zip`);
        await downloadReleaseZipball(releaseInfo.zipball_url, zipballPath);
        console.log(`Successfully downloaded zipball to: ${zipballPath}`);

        // Return the collected data
        return {
            releaseInfo,
            releaseNotes,
            adobeToken: tokenResponse
        };

    } catch (error) {
        console.error('Error:', error.message);
        process.exit(1);
    }
}

// Run the main function
main().then((result) => {
    console.log('\nProcess completed successfully');
    // The result object contains:
    // - releaseInfo: Full GitHub release object
    // - releaseNotes: Release notes string
    // - adobeToken: Adobe EQP token response
}).catch((error) => {
    console.error('Unexpected error:', error);
    process.exit(1);
});

module.exports = {
    getGithubRelease,
    getAdobeEQPAccessToken,
    makeRequest
};
