'use strict';

module.exports =

/**
 * Handles management of the (PHP) server that is needed to handle the server side.
 */
class ServerManager
{
    /**
     * @param {Object} phpInvoker
     * @param {String} folder
     */
    constructor(phpInvoker, folder) {
        this.phpInvoker = phpInvoker;
        this.folder = folder;

        this.distributionUploadHash = 'a67cdc349273c2389092f2036fb20d46';
    }

    /**
     * @return Promise
     */
    async install() {
        const download = require('download');

        // TODO: Serenata offers PHARs for each PHP version it supports, but for now we can get away with using the
        // lowest PHP version, as newer versions are backwards compatible enough.
        await download(
            `https://gitlab.com/Serenata/Serenata/uploads/${this.distributionUploadHash}/distribution-7.1.phar`,
            this.phpInvoker.normalizePlatformAndRuntimePath(this.getServerSourcePath()),
            {
                filename: 'distribution.phar',
            }
        );

        return new Promise((resolve, reject) => {
            const fs = require('fs');

            fs.writeFile(this.getVersionSpecificationFilePath(), this.versionSpecification, (error) => {
                if (error) {
                    reject(new Error(error.message));
                } else {
                    resolve();
                }
            });
        });
    }

    /**
     * @return {Boolean}
     */
    isInstalled() {
        const fs = require('fs');

        return fs.existsSync(this.getVersionSpecificationFilePath());
    }

    /**
     * @return {String}
     */
    getServerSourcePath() {
        if (this.folder === null || this.folder.length === 0) {
            throw new Error('Failed producing a usable server source folder path');
        } else if (this.folder === '/') {
            // Can never be too careful with dynamic path generation (and recursive deletes).
            throw new Error('Nope, I\'m not going to use your filesystem root');
        }

        return this.folder;
    }

    /**
     * @return {String}
     */
    getServerExecutablePath() {
        const path = require('path');

        return path.join(this.getServerSourcePath(), 'distribution.phar');
    }

    /**
     * @return {String}
     */
    getVersionSpecificationFilePath() {
        const path = require('path');

        return path.join(this.folder, this.distributionUploadHash);
    }
};
