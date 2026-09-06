<!-- kami:template auth_ui -->
<div class="kc-auth" data-auth>
    <div class="kc-auth-tabs" role="tablist" aria-label="{{phrase.login}} / {{phrase.register}}">
        <button class="kc-auth-tab is-active"
                type="button"
                role="tab"
                aria-selected="true"
                data-auth-mode="login">
            {{phrase.login}}
        </button>
        <button class="kc-auth-tab"
                type="button"
                role="tab"
                aria-selected="false"
                tabindex="-1"
                data-auth-mode="register">
            {{phrase.register}}
        </button>
    </div>

    <section data-auth-panel="login">
        <h2 id="login-modal-title" class="kc-modal-title">{{phrase.login}}</h2>

        <form action="UserAccount/login"
              method="post"
              data-ajax-form
              data-target="#login-result">
            <div class="kc-form-group">
                <label class="kc-form-label" for="account-login">
                    {{phrase.username_or_email}}
                </label>
                <input id="account-login"
                       class="kc-input"
                       name="login"
                       type="text"
                       placeholder="{{phrase.username_or_email}}"
                       autocomplete="username"
                       autofocus
                       required>
                <div class="kc-form-helper">{{phrase.login_help}}</div>
            </div>

            <div class="kc-form-group">
                <label class="kc-form-label" for="account-password">
                    {{phrase.password}}
                </label>
                <input id="account-password"
                       class="kc-input"
                       name="password"
                       type="password"
                       placeholder="{{phrase.password}}"
                       autocomplete="current-password"
                       required>
                <div class="kc-form-helper">{{phrase.password_case_sensitive}}</div>
            </div>

            <label class="kc-checkbox-label">
                <input class="kc-checkbox" name="remember" type="checkbox" value="1">
                <span>{{phrase.remember_me}}</span>
            </label>

            <button class="kc-btn kc-btn-primary kc-width-full" type="submit">
                {{phrase.login}}
            </button>
        </form>

        <button class="kc-btn kc-btn-link kc-width-full" style="margin-top:12px" type="button" data-auth-mode="password-reset">
            {{phrase.forgot_password}}
        </button>
    </section>

    <section data-auth-panel="password-reset" hidden>
        <h2 class="kc-modal-title">{{phrase.reset_password}}</h2>
        <p class="kc-form-helper">{{phrase.password_reset_request_help}}</p>

        <form action="UserAccount/request_password_reset"
              method="post"
              data-ajax-form
              data-target="#login-result">
            <div class="kc-form-group">
                <label class="kc-form-label" for="account-reset-identity">
                    {{phrase.username_or_email}}
                </label>
                <input id="account-reset-identity"
                       class="kc-input"
                       name="identity"
                       type="text"
                       placeholder="{{phrase.username_or_email}}"
                       autocomplete="username"
                       required>
            </div>

            <button class="kc-btn kc-btn-primary kc-width-full" type="submit">
                {{phrase.send_password_reset_link}}
            </button>
        </form>

        <button class="kc-btn kc-btn-link kc-width-full" style="margin-top:12px" type="button" data-auth-mode="login">
            {{phrase.back_to_login}}
        </button>
    </section>

    <section data-auth-panel="register" hidden>
        <h2 class="kc-modal-title">{{phrase.register}}</h2>

        <form action="UserAccount/register"
              method="post"
              data-ajax-form
              data-target="#login-result">
            <div class="kc-form-group">
                <label class="kc-form-label" for="account-username">{{phrase.username}}</label>
                <input id="account-username"
                       class="kc-input"
                       name="username"
                       type="text"
                       placeholder="{{phrase.username}}"
                       autocomplete="username"
                       required>
            </div>

            <div class="kc-form-group">
                <label class="kc-form-label" for="account-email">{{phrase.email}}</label>
                <input id="account-email"
                       class="kc-input"
                       name="useremail"
                       type="email"
                       placeholder="{{phrase.email}}"
                       autocomplete="email"
                       required>
            </div>

            <div class="kc-form-group">
                <label class="kc-form-label" for="account-new-password">{{phrase.password}}</label>
                <input id="account-new-password"
                       class="kc-input"
                       name="userpassword"
                       type="password"
                       placeholder="{{phrase.password}}"
                       autocomplete="new-password"
                       required>
            </div>

            <div class="kc-form-group">
                <label class="kc-form-label" for="account-repeat-password">{{phrase.repeat_password}}</label>
                <input id="account-repeat-password"
                       class="kc-input"
                       name="userpassword_repeat"
                       type="password"
                       placeholder="{{phrase.repeat_password}}"
                       autocomplete="new-password"
                       required>
            </div>

            <button class="kc-btn kc-btn-primary kc-width-full" type="submit">
                {{phrase.register}}
            </button>
        </form>
    </section>

    {{google_auth}}

    <div id="login-result" class="kc-auth-result" aria-live="polite"></div>
</div>
<!-- /kami:template -->
