# The interpreter the suite cannot run on.
#
# PHPUnit 9.6 needs PHP 7.3, so a library claiming to work on something older has no way to run anything
# there. This image lints the library and runs a small smoke script on the oldest PHP it supports.
#
# 5.5.38 is the last release of the 5.5 branch and the oldest PHP published as an official Docker image, and
# there is no arm64 build of it - so linux/amd64 under emulation, which is adequate for a lint and one round
# trip.
#
# Built and run by tests/run-legacy.sh; there is no need to invoke docker by hand.

# the platform is passed on the command line by run-legacy.sh rather than pinned here, which is what docker
# wants - a constant --platform in a FROM is reported as FromPlatformFlagConstDisallowed
FROM php:5.5-cli

# the extension the library is built around
RUN docker-php-ext-install mysqli > /dev/null

WORKDIR /library
