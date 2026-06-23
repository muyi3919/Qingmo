<?php
/**
 * 发布融合版文章
 * 运行：php gen-test.php
 */
require_once __DIR__ . '/includes/functions.php';

$nextId = next_post_id();
$now = time();

$title = '我用轻墨搭了一个博客（然后发现它居然能跑）';
$slug = 'build-a-retro-blog-v2';

$content = <<<'HTML'
<p>终于把这个博客搭起来了。准确说是花了一个下午随手写的，代码能跑就行，不要细看。</p>

<p>一开始只是心血来潮，想试试不用数据库、不用框架，只用原生 PHP 能做出什么东西。结果越写越上头——文章存在文件里，评论存在文件里，连配置都存在文件里。整个系统加起来不到 20 个 PHP 文件，纯纯的 handmade。</p>

<p>样式故意做得很朴素：宋体、灰边框、蓝色下划线链接，没有圆角没有渐变。放到现在看其实挺舒服的，至少不会被各种弹窗和悬浮广告追着跑。</p>

<p>这个博客会用来写点技术笔记和连载，也可能突然发生活吐槽。之前一直用 WordPress，功能很强大，但有时候就是想回归最简单的东西——打开即读，读完即走。</p>

<p>后台写了完整的文章/分类/评论管理，评论区可以正常使用。如果你也对这个项目的代码感兴趣，欢迎留言交流。</p>

<p>后续更新看心情，可能一周更一篇，可能一个月不更。反正大家随便看看，不用太认真。先占个位置 😋</p>
HTML;

save_post($nextId, [
    'id' => $nextId,
    'title' => $title,
    'slug' => $slug,
    'content' => $content,
    'summary' => '用原生 PHP 手搓了一个复古博客，无数据库无框架，代码能跑就行。更新看心情。',
    'category_id' => 3, // 技术笔记
    'author_id' => 1,
    'author_name' => 'admin',
    'status' => 1,
    'view_count' => 0,
    'comment_count' => 0,
    'tags' => ['PHP', '博客', '手搓'],
    'created_at' => $now,
    'updated_at' => $now,
]);

refresh_all_categories();

echo "文章已发布！ID: $nextId\n";
echo "访问: index.php?page=post&id=$nextId\n";
