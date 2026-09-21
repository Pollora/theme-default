/**
 * Frontend script for the %theme_name%/hero block.
 *
 * This script is loaded only on the frontend when the block is present.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

document.addEventListener('DOMContentLoaded', () => {
    const blocks = document.querySelectorAll('.wp-block-%theme_name%-hero');

    blocks.forEach((block) => {
        // Frontend interactivity here
    });
});
