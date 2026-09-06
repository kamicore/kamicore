<!-- kami:template page -->
<div>{{content}}</div>
<!-- /kami:template -->

<!-- kami:template single -->
<div>{{content}}</div>
<!-- /kami:template -->

<!-- kami:template default -->
<div>
	{{title}}
	{{content}}
</div>
<!-- /kami:template -->

<!-- kami:template responsive_grid -->
	{{title}}

	<div class="responsive_grid" style="--columns: {{max_items_per_row}}; --min-width: {{min_item_width}}px; --gap: 20px;">
		{{content}}
	</div>
</div>
<!-- /kami:template -->

<!-- kami:template card -->
<div class='card'>{{content}}</div>
<!-- /kami:template -->

<!-- kami:template block_title -->
<h3>{{content}}</h3>
<!-- /kami:template -->

<!-- kami:template articles-list -->
<div class="articles-list">
    {{items_per_page_selector}}
    <div class="articles-list-items">{{articles}}</div>
    {{pagination}}
</div>
<!-- /kami:template -->

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

<!-- kami:template article-card-preview -->
<a class="article-card-preview" href="{{url}}">
    <img src="{{preview}}" alt="{{alt}}" loading="lazy">
</a>
<!-- /kami:template -->

<!-- kami:template article-card-date -->
<time class="article-card-date" datetime="{{datetime}}">
    <svg class="icon icon-calendar icon-sm" aria-hidden="true"></svg>
    <span>{{date}}</span>
</time>
<!-- /kami:template -->

<!-- kami:template articles-empty -->
<p class="articles-empty">{{phrase.no_articles}}</p>
<!-- /kami:template -->

<!-- kami:template items-per-page-selector -->
<div class="card card-sm">
<form class="articles-per-page" method="get" action="{{action_url}}">
    <label>
        <span>{{label}}</span>
        <select class="kc-input kc-input-sm" name="{{select_name}}" onchange="this.form.submit()">{{options}}</select>
    </label>
</form>
</div>
<!-- /kami:template -->

<!-- kami:template items-per-page-option -->
<option value="{{value}}"{{selected}}>{{label}}</option>
<!-- /kami:template -->
