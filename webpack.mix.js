
let mix = require("laravel-mix");

// Imagemin webpack plugin
const ImageMinimizerPlugin = require('image-minimizer-webpack-plugin');
// Copy webpack plugin
const copyWebpackPlugin = require('copy-webpack-plugin');

mix.version();

if (!mix.inProduction()) {
  /* need both of these for sass sourcemaps to work */
  mix.webpackConfig({
    devtool: "inline-source-map",
  });
  mix.sourceMaps();
}

mix.setPublicPath("./"); // set because of this issue: https://github.com/JeffreyWay/laravel-mix/issues/1126
mix.js("assets/src/js/kekspay-admin.js", "assets/dist/js/kekspay-admin.js");
mix.js("assets/src/js/kekspay.js", "assets/dist/js/kekspay.js");

mix.sass("assets/src/scss/kekspay.scss", "assets/dist/css/kekspay.css");

// Images
// Mix doesn't have support for image minification out of the box so we have to modify webpack config.
mix.webpackConfig({
  plugins: [
    new copyWebpackPlugin({ // eslint-disable-line new-cap
      patterns: [
        {
          context: 'assets/src/img/',
          from: '**/*.{jpg,jpeg,png,gif,svg}',
          to: 'assets/dist/img',
        },
      ],
    }),
    new ImageMinimizerPlugin({
      test: [
        /\.(jpe?g|png|gif)$/i, // Image file extensions.
        /(?<!sprite-icons)\.svg$/i, // Separate RegEx for SVG but exclude sprite-icons.svg.
      ],
      minimizer: {
        implementation: ImageMinimizerPlugin.imageminMinify,
        options: {
          plugins: [
            [
              'gifsicle',
              {
                interlaced: true,
              },
            ],
            [
              'optipng',
              {
                optimizationLevel: 5,
              },
            ],
            'mozjpeg',
            'svgo',
          ],
        },
      },
    }),
  ],
});
