#!/bin/sh
# Stock nginx image ships default.conf ("Welcome to nginx!") which wins for IP/unknown Host.
rm -f /etc/nginx/conf.d/default.conf
