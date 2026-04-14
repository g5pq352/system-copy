module.exports = {
    proxy: "http://localhost/template-backend",
    port: 3000,
    open: false,
    notify: true,
    injectChanges: false,
    ui: false,
    ghostMode: false,
    files: [
        {
        match: [
            "template/**/*.php",
            "template/css/*.scss",
            "template/css/*.css",
            "template/**/*.js"
        ],
        // fn: function (event, file) { this.reload(); }   // 硬刷新
        },
    ],
    middleware: [
        function (req, res, next) {
            res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            res.setHeader('Pragma', 'no-cache');
            res.setHeader('Expires', '0');
            next();
        }
    ],
}
