# Flamodem - Self-hosting for Flameshot

## Server Setup
- Make sure PHP is installed on your server (tested on PHP 8.4.5).
- Copy `flamodem.config.php.example` to `flamodem.config.php`, adjust the configuration options and put it into your desired upload directory. Copy `index.php` as well.
- Make sure the upload directory is writable by your web server.

## Client setup
- Use this fork: https://github.com/aequabit/flameshot ([PKGBUILD](https://gist.githubusercontent.com/aequabit/62a85e528a14efa3366843fa8c0df59a/raw/5ba5b9c6f8ee1e78d56f0716a53b379c3910fc5a/PKGBUILD))
- Adjust `~/.config/flameshot/flameshot.ini`.
  - Set `uploadCustomUrl` to `BASE_URL`, as configured in `flamodem.config.php`. (Example: `https://image.example.org`)
  - Set `uploadClientSecret` to `AUTH_TOKEN`, as configured in `flamodem.config.php`.
  - To change the filename pattern, set `filenamePattern` according to the [strftime format](https://en.cppreference.com/w/cpp/chrono/c/strftime). (Default: `%F_%H-%M`)
  - For randomized filenames, set `uploadRandomFilename` to `true`. (Default: `false`)