module.exports = {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./resources/**/*.html",
        // Tour Slots Bulk Manager emits many utility classes only inside this JS file's
        // template strings; it lives in public/ (served statically) so must be listed explicitly.
        "./public/js/user-common/tour-slots-bulk-manager.js",
        "./public/js/user-common/mass-cancellation.js"
    ],
    theme: {
        extend: {
            fontFamily: {
                inter: ['Inter', 'sans-serif'],
            }
        },
    },
    plugins: [],
}
