# 公開側(chococo)の本番用。ビルドの起点は frontend/chococo(install.sh が取得する)。
# 開発用の chococo の docker/Dockerfile(npm run dev)とは別に、ビルドしてから Nuxt のサーバーを動かす
FROM node:24-slim AS build
WORKDIR /app
COPY src/package.json src/package-lock.json ./
RUN npm ci
COPY src/ ./
RUN npm run build

FROM node:24-slim
WORKDIR /app
ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=3000
COPY --from=build --chown=node:node /app/.output ./.output
USER node
EXPOSE 3000
CMD ["node", ".output/server/index.mjs"]
