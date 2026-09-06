<!DOCTYPE html>
<html lang="{{language_code}}">
<head>
{{template:pageheader}}
{{template:admin-pageheader}}
</head>


<body class="kami kami-admin" data-admin-shell>

<div class="admin-shell">
    <aside class="admin-sidebar" data-admin-sidebar>
        <div class="logo">
			<img src="/brand/logo/kamicore-logo-white.svg" />
            <span>Kami</span>
        </div>

        <nav class="menu">
            {{sidebar}}
        </nav>

        <button class="sidebar-collapse-button"
                type="button" data-admin-sidebar-toggle
                aria-label="Toggle sidebar" aria-expanded="true">
            <svg class="icon icon-chevron-left icon-lg"></svg>
        </button>
    </aside>

    <div class="admin-area">
        <div class="kc-container">
            <div class="kc-navbar">
				<div></div>
                <div class="kc-navbar-right">
                    {{content_top}}
                </div>
            </div>
            {{content_middle}}
        </div>

        {{template:admin-footer}}
    </div>
</div>

</body>
</html>
