FROM node:22-alpine AS build
WORKDIR /web
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
ARG VITE_GOOGLE_MAPS_KEY
ARG VITE_ANDROID_DOWNLOAD_URL
ARG VITE_IOS_DOWNLOAD_URL
ARG VITE_PUBLIC_SITE_URL
RUN npm run build
FROM nginx:stable-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /web/dist /usr/share/nginx/html
