# Vite 类 Laravel 丝滑集成设计

日期：2026-02-03

## 目标
- 在 Spiral + Twig 项目中实现类似 Laravel 的 Vite 集成体验：开发态 HMR、生产态 manifest 解析、统一模板调用。
- 支持多入口（`resources/js/app.js`、`resources/css/tailwind.css`、`resources/css/fonts.css`）。
- 支持 `build` 目录切换、`inline` 内联输出、模块预加载（modulepreload）。
- 适配 RoadRunner 常驻进程的缓存失效。

## 非目标
- SSR 与框架级别的 Vite 插件生态。
- 自动网络探测 dev server（以 `hot` 文件为准）。
- 复杂的资源完整性校验（可后续扩展）。

## 现状
- 已有 `vite.config.js` 与 Tailwind 插件。
- `resources/js/app.js` 为空；CSS 入口为 `resources/css/tailwind.css`、`resources/css/fonts.css`。
- `app/views/layout/base.twig` 未注入任何资源。

## 方案对比与选择
1) **hot + manifest（推荐）**
   - Dev：Vite 启动时写 `public/hot`，Twig 检测该文件决定是否注入 `@vite/client`。
   - Prod：读取 `public/build/.vite/manifest.json` 输出 hash 资源。
   - 优点：行为贴近 Laravel，简单可靠；无需网络探测。
2) 环境变量驱动（`VITE_DEV_SERVER_URL`）
   - 需要开发者手动配置，易遗漏。
3) 运行时探测 dev server
   - 引入网络超时与误判风险。

## 推荐方案细节
### 1) Vite 构建配置
- `build.outDir = 'public/build'`
- `build.manifest = true`
- `build.assetsDir = 'assets'`
- `rollupOptions.input` 显式列出多入口：
  - `resources/js/app.js`
  - `resources/css/tailwind.css`
  - `resources/css/fonts.css`

### 2) Dev 热更新标识（hot 文件）
- Vite 插件在 `configureServer` 写入 `public/hot`（内容为 dev server URL）。
- 在 `buildEnd` 或 `closeBundle` 删除 `public/hot`，避免残留。

### 3) 后端解析层（Vite 服务类）
- 新增 `App\Infrastructure\Vite\Vite`：
  - `isDev()`：检查 `public/hot` 是否存在。
  - `manifest()`：读取 `public/build/.vite/manifest.json` 并缓存（按 `filemtime` 失效）。
  - `tags(entries, buildDir='build', inline=false)`：输出脚本、样式与 preload 标签。
  - `asset(path, buildDir='build')`：返回 manifest 中的资源 URL。
- 生产态输出：
  - `<script type="module">`（入口 JS）
  - `<link rel="stylesheet">`（入口/依赖 CSS）
  - `<link rel="modulepreload">`（入口 imports）
- `inline=true`：读取构建产物文件内容并内联（仅生产态）。

### 4) Twig 集成
- 新增 Twig Extension（如 `App\Infrastructure\Vite\Twig\ViteExtension`）
  - `vite(entries, buildDir='build', inline=false)` -> `Twig\Markup`
  - `vite_asset(path, buildDir='build')`
- 在 Bootloader 中注册扩展（`TwigBootloader->addExtension`）。

### 5) 错误处理
- Dev：仅依据 `public/hot`；若 dev server 不可达仍输出 HMR 标签，保持 Laravel 一致性。
- Prod：manifest 缺失或入口不存在时抛异常，提示执行 `npm run build`。

### 6) 缓存策略
- 常驻进程内存缓存 manifest；对比 `filemtime` 失效。

## 数据流（请求到模板输出）
1) Twig 调用 `vite()`
2) 若存在 `public/hot`：输出 `@vite/client` + dev 入口资源
3) 否则读取 manifest：输出 JS/CSS/preload；若 `inline` 则内联内容

## 测试策略
- 单元测试：manifest 解析（入口、imports、css）与 `inline` 行为。
- 特性测试：Twig 生成标签（dev/prod、多入口顺序）。

## 变更清单（实现阶段）
- `vite.config.js`：加入多入口、manifest、outDir 与热文件插件。
- 新增：`app/src/Infrastructure/Vite/*` 与 Twig 扩展。
- 更新：`app/views/layout/base.twig` 使用 `vite()`。
- 新增：`app/config/vite.php`（或通过构造参数注入）。

## 风险与后续
- Vite 版本升级可能改变 manifest 结构：需在测试覆盖关键字段。
- 若未来引入多 build 目录或 SSR，可扩展 `buildDir` 参数与服务类。
