const path = require('path');
const fs = require('fs');
const https = require('https');
const { pipeline } = require('stream/promises');
const crypto = require('crypto');

const GITHUB_API_URL = 'api.github.com';
const ADOBE_EQP_API_URL = 'commercedeveloper-api.adobe.com';
const GITHUB_TOKEN = process.env.GITHUB_TOKEN;
const GITHUB_REPO = process.env.GITHUB_REPO;
const RELEASE_TAG = process.env.RELEASE_TAG;
const ADOBE_EQP_APP_ID = process.env.ADOBE_EQP_APP_ID;
const ADOBE_EQP_APP_SECRET = process.env.ADOBE_EQP_APP_SECRET;
const ADOBE_EQP_PACKAGE_SKU = process.env.ADOBE_EQP_PACKAGE_SKU;
const LICENSE_TYPE = process.env.LICENSE_TYPE || 'mit';

/**
 * Cleans up release notes by removing GitHub-generated comments and emojis
 *
 * @param {string} notes - Raw release notes from GitHub
 *
 * @returns {string} - Cleaned release notes
 */
function cleanupReleaseNotes(notes) {
    if (!notes) return '';

    return notes
        .replace(/<!-- Release notes generated using configuration in \.github\/release\.yml at .* -->/g, '')
        .replace(/💎/g, '')
        .replace(/🖇️/g, '')
        .replace(/⛑️/g, '')
        .trim();
}

/**
 * Makes an HTTPS request and returns a promise
 *
 * @param {Object} options - Request options
 * @param {string|null} postData - Data to send in POST request
 *
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
 * @returns {Promise<Object>} - Release information
 */
async function getGithubRelease() {
    const options = {
        hostname: GITHUB_API_URL,
        path: `/repos/${GITHUB_REPO}/releases/tags/${RELEASE_TAG}`,
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${GITHUB_TOKEN}`,
            'User-Agent': 'Adyen-Magento2-Release-Script'
        }
    };

    return makeRequest(options);
}

/**
 * Gets access token from Adobe EQP API
 *
 * @returns {Promise<Object>} - Access token response
 */
async function getAdobeEQPAccessToken() {
    const credentials = Buffer.from(`${ADOBE_EQP_APP_ID}:${ADOBE_EQP_APP_SECRET}`).toString('base64');

    const postData = JSON.stringify({
        grant_type: "session",
        expires_in: 300
    });

    const options = {
        hostname: ADOBE_EQP_API_URL,
        path: '/rest/v1/app/session/token',
        method: 'POST',
        headers: {
            'Authorization': `Basic ${credentials}`,
            'Content-Type': 'application/json',
            'Content-Length': Buffer.byteLength(postData)
        }
    };

    return makeRequest(options, postData);
}

/**
 * Downloads a release zipball from GitHub and saves it to a file
 *
 * @param {string} url - The zipball URL from GitHub
 * @param {string} destPath - Destination file path
 *
 * @returns {Promise<void>}
 */
async function downloadReleaseZipball(url, destPath) {
    const response = await new Promise((resolve, reject) => {
        https.get(url, {
            headers: {
                'Authorization': `Bearer ${GITHUB_TOKEN}`,
                'User-Agent': 'Adyen-Magento2-Release-Script',
                'Accept': 'application/vnd.github+json'
            }
        }, resolve).on('error', reject);
    });

    if (response.statusCode >= 300 && response.statusCode < 400 && response.headers.location) {
        return downloadReleaseZipball(response.headers.location, destPath);
    }

    if (response.statusCode !== 200) {
        throw new Error(`Failed to download the zipball!`);
    }

    await pipeline(response, fs.createWriteStream(destPath));
}

/**
 * Uploads a zipball to Adobe EQP and returns the file upload ID
 *
 * @param {string} accessToken - Adobe EQP access token
 * @param {string} filePath - Path to the zip file to upload
 *
 * @returns {Promise<Object>} - Upload response containing file_upload_id
 */
function uploadZipballToAdobeEQP(accessToken, filePath) {
    const fileName = path.basename(filePath);
    const fileBuffer = fs.readFileSync(filePath);
    const boundary = crypto.randomUUID();

    const bodyStart = Buffer.from(
        `--${boundary}\r\n` +
        `Content-Disposition: form-data; name="file[]"; filename="${fileName}"\r\n` +
        `Content-Type: application/zip\r\n\r\n`
    );
    const bodyEnd = Buffer.from(`\r\n--${boundary}--`);
    const body = Buffer.concat([bodyStart, fileBuffer, bodyEnd]);

    const options = {
        hostname: ADOBE_EQP_API_URL,
        path: '/rest/v1/files/uploads',
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${accessToken}`,
            'Content-Type': `multipart/form-data; boundary=${boundary}`,
            'Content-Length': body.length
        }
    };

    return makeRequest(options, body);
}

/**
 * Checks the status of a file upload on Adobe EQP
 *
 * @param {string} accessToken - Adobe EQP access token
 * @param {string} fileUploadId - The file upload ID to check
 *
 * @returns {Promise<Object>} - Upload status response
 */
function getFileUploadStatus(accessToken, fileUploadId) {
    const options = {
        hostname: ADOBE_EQP_API_URL,
        path: `/rest/v1/files/uploads/${fileUploadId}`,
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${accessToken}`
        }
    };

    return makeRequest(options);
}

/**
 * Waits for malware scan to complete with polling
 *
 * @param {string} accessToken - Adobe EQP access token
 * @param {string} fileUploadId - The file upload ID to check
 * @param {number} timeoutMs - Maximum time to wait in milliseconds (default: 120000)
 * @param {number} intervalMs - Polling interval in milliseconds (default: 10000)
 *
 * @returns {Promise<Object>} - Final upload status response
 */
async function waitForMalwareScan(
    accessToken,
    fileUploadId,
    timeoutMs = 240000,
    intervalMs = 15000
) {
    const startTime = Date.now();

    while (Date.now() - startTime < timeoutMs) {
        const status = await getFileUploadStatus(accessToken, fileUploadId);
        const malwareStatus = status.malware_status;

        if (malwareStatus === 'passed') {
            return status;
        }

        if (malwareStatus === 'in-progress' || malwareStatus === 'queued') {
            await new Promise(resolve => setTimeout(resolve, intervalMs));
            continue;
        }

        throw new Error(`Unexpected malware_status: ${malwareStatus}`);
    }

    throw new Error(`Malware scan timed out after ${timeoutMs / 1000} seconds`);
}

/**
 * Submits a package to Adobe EQP Marketplace
 *
 * @param {string} accessToken - Adobe EQP access token
 * @param {string} fileUploadId - The file upload ID from uploadZipballToAdobeEQP
 * @param {string} version - Package version
 * @param {string} releaseNotes - Release notes for the package
 *
 * @returns {Promise<Object>} - Submission response
 */
function submitPackageToAdobeEQP(
    accessToken,
    fileUploadId,
    version,
    releaseNotes
) {
    const payload = [
        {
            action: {
                technical: "submit"
            },
            type: "extension",
            platform: "M2",
            release_notes: releaseNotes,
            version: version,
            artifact: {
                file_upload_id: fileUploadId
            },
            license_type: LICENSE_TYPE,
            sku: ADOBE_EQP_PACKAGE_SKU
        }
    ];

    const postData = JSON.stringify(payload);

    const options = {
        hostname: ADOBE_EQP_API_URL,
        path: '/rest/v1/products/packages',
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${accessToken}`,
            'Content-Type': 'application/json',
            'Content-Length': Buffer.byteLength(postData)
        }
    };

    return makeRequest(options, postData);
}

/**
 * Main function to orchestrate the release process
 */
async function main() {
    if (!RELEASE_TAG || !ADOBE_EQP_APP_ID || !ADOBE_EQP_APP_SECRET || !GITHUB_TOKEN) {
        console.error('Missing required environment variables');
        process.exit(1);
    }

    let releaseNotes = '';

    try {
        // Fetch release information from GitHub and access token from Adobe EQP
        const releaseInfo = await getGithubRelease();
        const tokenResponse = await getAdobeEQPAccessToken();

        // Download the release zipball from GitHub
        const zipballPath = path.join(process.cwd(), `${RELEASE_TAG}.zip`);
        await downloadReleaseZipball(releaseInfo.zipball_url, zipballPath);

        // Upload the zipball to Adobe EQP
        const [{ file_upload_id }] = await uploadZipballToAdobeEQP(tokenResponse.ust, zipballPath);

        // Wait for malware scan to complete
        await waitForMalwareScan(tokenResponse.ust, file_upload_id);

        // Submit the package to Adobe EQP
        const submitResponse = await submitPackageToAdobeEQP(
            tokenResponse.ust,
            file_upload_id,
            releaseInfo.tag_name.replace(/^v/, ''),
            cleanupReleaseNotes(releaseInfo.body)
        );

        return;
    } catch (error) {
        process.exit(1);
    }
}

// Run the main function
main().then(() => {
    console.log('Process completed successfully!');
}).catch((error) => {
    console.error('Unexpected error!');
    process.exit(1);
});
