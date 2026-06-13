# syntax=docker/dockerfile:1.7

FROM nginx:1.27-alpine

COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf
COPY apps/web/public /var/www/html/public
