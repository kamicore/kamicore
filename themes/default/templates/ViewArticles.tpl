<!-- kami:template article-card -->
<article class="article-card">
    {{preview}}
    <div class="article-card-content">
        <h2 class="article-card-title"><a href="{{url}}">{{title}}</a></h2>
        {{published_at}}
        <p class="article-card-summary">{{summary}}</p>
        <a class="article-card-more" href="{{url}}">{{phrase.read_more}}</a>
    </div>
</article>
<!-- /kami:template -->

<!-- kami:template article-page -->
<div class="article-page">
	<div class="article-preview">
		<img src="{{article_preview}}">
	</div>
	<div class="article-headbox">
		<h1>{{title}}</h1>

		<time class="article-date" datetime="{{published_at_iso}}">
			<svg class="icon icon-calendar icon-sm" aria-hidden="true"></svg>
			<span>{{published_at}}</span>
		</time>

		<aside>{{summary}}</aside>
	</div>

	<div class="article-body">
		{{article_body}}
	</div>
</div>
<!-- /kami:template -->
