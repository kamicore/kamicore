<!-- kami:template authorized -->
<div class="kc-account-menu" data-menu>
    <button class="kc-icon-button"
            type="button"
            data-menu-toggle
            aria-expanded="false"
            aria-label="Open user menu">
        <svg class="icon icon-user-round icon-lg"></svg>
    </button>

    <div id="usermenu" class="kc-dropdown" data-menu-panel hidden>
        {{user_context_menu}}
    </div>
</div>
<!-- /kami:template -->

<!-- kami:template guest -->
<div class="kc-auth-entry">
    <button class="kc-icon-button"
            type="button"
            data-modal-open="login-modal"
            aria-controls="login-modal"
            title="{{phrase.login}} / {{phrase.register}}">
        <svg class="icon icon-power icon-lg"></svg>
        <span class="kc-visually-hidden">{{phrase.login}} / {{phrase.register}}</span>
    </button>
</div>

<div id="login-modal"
     class="kc-modal"
     data-modal
     hidden
     role="dialog"
     aria-modal="true"
     aria-labelledby="login-modal-title">
    <div class="kc-modal-backdrop" data-modal-close></div>

    <div class="kc-modal-dialog">
        <button class="kc-modal-close"
                type="button"
                data-modal-close
                aria-label="Close">&times;</button>

        {{auth}}
    </div>
</div>
<!-- /kami:template -->

<!-- kami:template status -->
<div class="kc-user-status">
    <span class="kc-user-name">{{phrase.welcome}} {{username}}</span>
    {{user_actions_icon}}
    {{user_badges}}
</div>
<!-- /kami:template -->

<!-- kami:template sidebar_menu -->
<div class="kc-profile-sidebar">
    {{profile_page}}
</div>
<!-- /kami:template -->

<!-- kami:template preferences -->
<div class="kc-profile-preferences"></div>
<!-- /kami:template -->

<!-- kami:template credentials -->
<section class="kc-profile-credentials" aria-labelledby="kc-profile-credentials-title">
    <header class="kc-profile-credentials-header">
        <h2 id="kc-profile-credentials-title">{{phrase.credentials}}</h2>
        <p>{{phrase.credentials_intro}}</p>
    </header>

    <div id="kc-profile-credentials-content">
        {{content}}
    </div>
</section>
<!-- /kami:template -->

<!-- kami:template credentials_content -->
{{notice}}

<div class="kc-profile-credentials-grid">
    <section class="kc-profile-credential-card">
        <div class="kc-profile-credential-heading">
            <div>
                <h3>{{phrase.username}}</h3>
                <p>{{phrase.username_help}}</p>
            </div>
        </div>

        <form action="UserProfile/change_username"
              method="post"
              data-ajax-form
              data-target="#kc-profile-credentials-content">
            <input type="hidden" name="return_url" value="{{return_url}}">
            <div class="kc-profile-inline-form">
                <input class="kc-input"
                       type="text"
                       name="username"
                       value="{{username}}"
                       autocomplete="username"
                       pattern="[A-Za-z0-9._\-]+"
                       required>
                <button class="kc-btn kc-btn-primary" type="submit">{{phrase.save}}</button>
            </div>
        </form>
    </section>

    <section class="kc-profile-credential-card">
        <div class="kc-profile-credential-heading">
            <div>
                <h3>{{phrase.email}}</h3>
                <p>{{phrase.email_change_help}}</p>
            </div>
            <span class="kc-profile-status {{email_status_class}}">{{email_status}}</span>
        </div>

        <form action="UserProfile/change_email"
              method="post"
              data-ajax-form
              data-target="#kc-profile-credentials-content">
            <input type="hidden" name="return_url" value="{{return_url}}">
            <div class="kc-profile-inline-form">
                <input class="kc-input"
                       type="email"
                       name="email"
                       value="{{email}}"
                       autocomplete="email"
                       required>
                <button class="kc-btn kc-btn-primary" type="submit">{{phrase.change_email}}</button>
            </div>
        </form>

        {{pending_email}}
    </section>

    <section class="kc-profile-credential-card kc-profile-credential-card-wide">
        <div class="kc-profile-credential-heading">
            <div>
                <h3>{{phrase.password}}</h3>
                <p>{{phrase.password_help}}</p>
            </div>
        </div>

        {{password}}
    </section>

    <section class="kc-profile-credential-card kc-profile-credential-card-wide">
        <div class="kc-profile-credential-heading">
            <div>
                <h3>{{phrase.sign_in_methods}}</h3>
                <p>{{phrase.sign_in_methods_help}}</p>
            </div>
        </div>

        <div class="kc-profile-provider-list">
            {{google_provider}}
        </div>
    </section>
</div>
<!-- /kami:template -->

<!-- kami:template pending_email -->
<div class="kc-profile-pending">
    <strong>{{phrase.pending_email}}:</strong> {{email}}
    <span>{{phrase.pending_email_help}}</span>
</div>
<!-- /kami:template -->

<!-- kami:template password_configured -->
<div class="kc-profile-method-status">
    <span class="kc-profile-status is-success">{{phrase.configured}}</span>
</div>

<form action="UserProfile/change_password"
      method="post"
      data-ajax-form
      data-target="#kc-profile-credentials-content"
      class="kc-profile-password-form">
    <input type="hidden" name="return_url" value="{{return_url}}">
    <div class="kc-form-group">
        <label class="kc-form-label" for="profile-current-password">{{phrase.current_password}}</label>
        <input class="kc-input"
               id="profile-current-password"
               type="password"
               name="current_password"
               autocomplete="current-password"
               required>
    </div>

    <div class="kc-profile-password-grid">
        <div class="kc-form-group">
            <label class="kc-form-label" for="profile-new-password">{{phrase.new_password}}</label>
            <input class="kc-input"
                   id="profile-new-password"
                   type="password"
                   name="new_password"
                   autocomplete="new-password"
                   minlength="8"
                   required>
        </div>

        <div class="kc-form-group">
            <label class="kc-form-label" for="profile-new-password-repeat">{{phrase.repeat_password}}</label>
            <input class="kc-input"
                   id="profile-new-password-repeat"
                   type="password"
                   name="new_password_repeat"
                   autocomplete="new-password"
                   minlength="8"
                   required>
        </div>
    </div>

    <div class="kc-btn-row is-left">
        <button class="kc-btn kc-btn-primary" type="submit">{{phrase.change_password}}</button>
    </div>
</form>

<details class="kc-profile-danger-zone">
    <summary>{{phrase.remove_password}}</summary>
    <div class="kc-profile-danger-content">
        <p>{{phrase.remove_password_help}}</p>
        <form action="UserProfile/remove_password"
              method="post"
              data-ajax-form
              data-target="#kc-profile-credentials-content">
            <input type="hidden" name="return_url" value="{{return_url}}">
            <div class="kc-profile-inline-form">
                <input class="kc-input"
                       type="password"
                       name="current_password"
                       autocomplete="current-password"
                       placeholder="{{phrase.current_password}}"
                       required>
                <button class="kc-btn kc-btn-danger" type="submit">{{phrase.remove_password}}</button>
            </div>
        </form>
    </div>
</details>
<!-- /kami:template -->

<!-- kami:template password_not_configured -->
<div class="kc-profile-method-status">
    <span class="kc-profile-status is-muted">{{phrase.not_configured}}</span>
</div>

<form action="UserProfile/change_password"
      method="post"
      data-ajax-form
      data-target="#kc-profile-credentials-content"
      class="kc-profile-password-form">
    <input type="hidden" name="return_url" value="{{return_url}}">
    <div class="kc-profile-password-grid">
        <div class="kc-form-group">
            <label class="kc-form-label" for="profile-set-password">{{phrase.new_password}}</label>
            <input class="kc-input"
                   id="profile-set-password"
                   type="password"
                   name="new_password"
                   autocomplete="new-password"
                   minlength="8"
                   required>
        </div>

        <div class="kc-form-group">
            <label class="kc-form-label" for="profile-set-password-repeat">{{phrase.repeat_password}}</label>
            <input class="kc-input"
                   id="profile-set-password-repeat"
                   type="password"
                   name="new_password_repeat"
                   autocomplete="new-password"
                   minlength="8"
                   required>
        </div>
    </div>

    <div class="kc-btn-row is-left">
        <button class="kc-btn kc-btn-primary" type="submit">{{phrase.set_password}}</button>
    </div>
</form>
<!-- /kami:template -->

<!-- kami:template provider_connected -->
<div class="kc-profile-provider">
    <div class="kc-profile-provider-main">
        <strong>{{provider_name}}</strong>
        <span>{{provider_identity}}</span>
    </div>

    <div class="kc-btn-group">
        <a class="kc-btn kc-btn-secondary" href="{{replace_url}}">{{phrase.replace}}</a>
        <form action="UserProfile/disconnect_provider"
              method="post"
              data-ajax-form
              data-target="#kc-profile-credentials-content"
              onsubmit="return confirm('{{phrase.disconnect_confirm}}')">
            <input type="hidden" name="return_url" value="{{return_url}}">
            <input type="hidden" name="provider" value="{{provider}}">
            <button class="kc-btn kc-btn-ghost" type="submit">{{phrase.disconnect}}</button>
        </form>
    </div>
</div>
<!-- /kami:template -->

<!-- kami:template provider_not_connected -->
<div class="kc-profile-provider">
    <div class="kc-profile-provider-main">
        <strong>{{provider_name}}</strong>
        <span>{{phrase.not_connected}}</span>
    </div>

    <a class="kc-btn kc-btn-primary" href="{{connect_url}}">{{phrase.connect}}</a>
</div>
<!-- /kami:template -->

<!-- kami:template credentials_notice -->
<div class="kc-notice {{notice_class}} kc-profile-notice" role="status">
    {{message}}
</div>
<!-- /kami:template -->

<!-- kami:template credentials_error -->
<div class="kc-notice kc-notice-error" role="alert">{{message}}</div>
<!-- /kami:template -->
