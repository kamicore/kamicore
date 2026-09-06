<!-- kami:template overview -->
<div class="tm-shell">
  <section class="tm-section">
    <div class="tm-heading"><h2>{{phrase.system_entities}}</h2></div>
    <div class="tm-grid">{{system_cards}}</div>
  </section>
  <section class="tm-section">
    <div class="tm-heading"><h2>{{phrase.content}}</h2></div>
    <div class="tm-grid">{{content_cards}}</div>
  </section>
</div>
<!-- /kami:template -->

<!-- kami:template system-card -->
<a class="tm-card" href="{{url}}">
  <div class="tm-card-title">{{title}}</div>
  <div class="tm-muted">{{count}}</div>
</a>
<!-- /kami:template -->

<!-- kami:template content-card -->
<a class="tm-card" href="{{url}}">
  <div class="tm-card-title">{{title}}</div>
  <div class="tm-muted">{{system_name}} · {{count}}</div>
</a>
<!-- /kami:template -->

<!-- kami:template system-list -->
<div class="tm-shell">
  <div class="tm-heading">
    <div>
      <a class="admin-back-link" href="{{back_url}}">
        <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
        <span>{{back_label}}</span>
      </a>
      <h2>{{title}}</h2>
    </div>
  </div>
  <form class="tm-toolbar admin-panel" method="get" action="{{load_url}}">
    <div class="tm-field">
      <label>{{phrase.source_language}}</label>
      {{language_select}}
    </div>
    <button class="admin-button admin-button-primary" type="submit">{{phrase.load}}</button>
  </form>
  <div class="tm-list">{{items}}</div>
</div>
<!-- /kami:template -->

<!-- kami:template system-row -->
<a class="tm-list-row" href="{{url}}">
  <span>{{title}}</span><span class="tm-muted">{{system_name}}</span>
</a>
<!-- /kami:template -->

<!-- kami:template content-list -->
<div class="tm-shell">
  <div class="tm-heading">
    <div>
      <a class="admin-back-link" href="{{back_url}}">
        <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
        <span>{{back_label}}</span>
      </a>
      <h2>{{title}}</h2>
    </div>
  </div>
  <form class="tm-toolbar admin-panel" method="get" action="{{load_url}}">
    <div class="tm-field">
      <label>{{phrase.source_language}}</label>
      {{language_select}}
    </div>
    <button class="admin-button admin-button-primary" type="submit">{{phrase.load}}</button>
  </form>
  <div class="tm-list">{{items}}</div>
  {{pagination}}
</div>
<!-- /kami:template -->

<!-- kami:template content-row -->
<a class="tm-list-row" href="{{url}}">
  <span>{{title}}</span><span class="tm-muted">{{slug}}</span>
</a>
<!-- /kami:template -->


<!-- kami:template dictionary-edit -->
<div class="tm-shell">
  <div class="tm-heading">
    <div>
      <a class="admin-back-link" href="{{back_url}}">
        <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
        <span>{{back_label}}</span>
      </a>
      <h2>{{entity_title}}</h2>
    </div>
  </div>
  {{notice}}
  <form class="tm-editor" method="post">
    <section class="admin-panel tm-config">
      <div class="tm-field"><label>{{phrase.source_language}}</label>{{source_select}}</div>
      <div class="tm-field"><label>{{phrase.target_language}}</label>{{target_select}}</div>
      <div class="tm-field"><label>{{phrase.provider}}</label>{{provider_select}}</div>
      <div class="tm-field"><label>&nbsp;</label><button class="admin-button admin-button-secondary" type="submit" formaction="{{reload_url}}">{{phrase.load}}</button></div>
    </section>
    <section class="admin-panel tm-prompt">
      <div class="tm-field"><label>{{phrase.context}}</label><textarea class="admin-input admin-textarea" name="trm-context">{{context}}</textarea></div>
      <div class="tm-field"><label>{{phrase.instructions}}</label><textarea class="admin-input admin-textarea" name="trm-instructions">{{instructions}}</textarea></div>
    </section>
    <div class="tm-dictionary-head">
      <div>{{phrase.phrase_key}}</div>
      <div>{{phrase.source}}</div>
      <div>{{phrase.translation}}</div>
      <div>{{phrase.delete}}</div>
    </div>
    <div class="tm-section">{{rows}}</div>
    <section class="admin-panel tm-dictionary-new">
      <h3>{{phrase.new_phrase}}</h3>
      <div class="tm-dictionary-new-grid">
        <div class="tm-field">
          <label>{{phrase.phrase_key}}</label>
          <input class="admin-input" type="text" name="trm-new-key" placeholder="example_phrase_key">
        </div>
        <div class="tm-field">
          <label>{{phrase.source}}</label>
          <textarea class="admin-input admin-textarea" name="trm-new-source"></textarea>
        </div>
        <div class="tm-field">
          <label>{{phrase.translation}}</label>
          <textarea class="admin-input admin-textarea" name="trm-new-target"></textarea>
        </div>
      </div>
    </section>
    <footer class="admin-form-actions tm-editor-actions">
      <div class="tm-action-group">
        <button class="admin-button admin-button-action" type="submit" formaction="{{translate_url}}">{{phrase.translate}}</button>
        <button class="admin-button admin-button-primary" type="submit" formaction="{{save_url}}">{{phrase.save}}</button>
      </div>
    </footer>
  </form>
</div>
<!-- /kami:template -->

<!-- kami:template dictionary-row -->
<div class="tm-row tm-dictionary-row">
  <div class="tm-dictionary-key"><code>{{phrase_key}}</code></div>
  <div><textarea class="admin-input admin-textarea" name="{{source_name}}">{{source_value}}</textarea></div>
  <div><textarea class="admin-input admin-textarea" name="{{target_name}}">{{target_value}}</textarea></div>
  <label class="tm-dictionary-delete" title="{{delete_label}}">
    <input type="checkbox" name="{{delete_name}}" value="1">
    <span class="tm-dictionary-delete-label">{{delete_label}}</span>
  </label>
</div>
<!-- /kami:template -->

<!-- kami:template system-edit -->
<div class="tm-shell">
  <div class="tm-heading">
    <div>
      <a class="admin-back-link" href="{{back_url}}">
        <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
        <span>{{back_label}}</span>
      </a>
      <h2>{{entity_title}}</h2>
    </div>
  </div>
  {{notice}}
  <form class="tm-editor" method="post">
    <section class="admin-panel tm-config">
      <div class="tm-field"><label>{{phrase.source_language}}</label>{{source_select}}</div>
      <div class="tm-field"><label>{{phrase.target_language}}</label>{{target_select}}</div>
      <div class="tm-field"><label>{{phrase.provider}}</label>{{provider_select}}</div>
      <div class="tm-field"><label>&nbsp;</label><button class="admin-button admin-button-secondary" type="submit" formaction="{{reload_url}}">{{phrase.load}}</button></div>
    </section>
    <section class="admin-panel tm-prompt">
      <div class="tm-field"><label>{{phrase.context}}</label><textarea class="admin-input admin-textarea" name="trm-context">{{context}}</textarea></div>
      <div class="tm-field"><label>{{phrase.instructions}}</label><textarea class="admin-input admin-textarea" name="trm-instructions">{{instructions}}</textarea></div>
    </section>
    <div class="tm-translation-head"><div>{{phrase.source}}</div><div>{{phrase.translation}}</div></div>
    <div class="tm-section">{{rows}}</div>
    <footer class="admin-form-actions tm-editor-actions">
      <div class="tm-action-group">
        <button class="admin-button admin-button-action" type="submit" formaction="{{translate_url}}">{{phrase.translate}}</button>
        <button class="admin-button admin-button-primary" type="submit" formaction="{{save_url}}">{{phrase.save}}</button>
      </div>
    </footer>
  </form>
</div>
<!-- /kami:template -->

<!-- kami:template translation-row -->
<div class="tm-row tm-translation-row">
  <div><div class="tm-path">{{label}}</div><div class="tm-source">{{source_value}}</div></div>
  <div><textarea class="admin-input admin-textarea" name="{{field_name}}">{{target_value}}</textarea></div>
</div>
<!-- /kami:template -->

<!-- kami:template content-edit -->
<div class="tm-shell">
  <div class="tm-heading">
    <div>
      <a class="admin-back-link" href="{{back_url}}">
        <svg class="icon icon-chevron-left icon-sm" aria-hidden="true"></svg>
        <span>{{back_label}}</span>
      </a>
      <h2>{{entity_title}}</h2>
    </div>
  </div>
  {{notice}}
  <form class="tm-editor" method="post">
    <section class="admin-panel tm-config">
      <div class="tm-field"><label>{{phrase.source_language}}</label>{{source_select}}</div>
      <div class="tm-field"><label>{{phrase.target_language}}</label>{{target_select}}</div>
      <div class="tm-field"><label>{{phrase.provider}}</label>{{provider_select}}</div>
      <div class="tm-field"><label>&nbsp;</label><button class="admin-button admin-button-secondary" type="submit" formaction="{{reload_url}}">{{phrase.load}}</button></div>
    </section>
    <section class="admin-panel tm-prompt">
      <div class="tm-field"><label>{{phrase.context}}</label><textarea class="admin-input admin-textarea" name="trm-context">{{context}}</textarea></div>
      <div class="tm-field"><label>{{phrase.instructions}}</label><textarea class="admin-input admin-textarea" name="trm-instructions">{{instructions}}</textarea></div>
    </section>
    <div class="tm-translation-head"><div>{{phrase.source}}</div><div>{{phrase.translation}}</div></div>
    <div class="tm-section">{{rows}}</div>
    <footer class="admin-form-actions tm-editor-actions">
      <div class="tm-action-group">
        <button class="admin-button admin-button-action" type="submit" formaction="{{translate_url}}">{{phrase.translate}}</button>
        <button class="admin-button admin-button-primary" type="submit" formaction="{{save_url}}">{{phrase.save}}</button>
      </div>
    </footer>
  </form>
</div>
<!-- /kami:template -->

<!-- kami:template content-translation-row -->
<div class="tm-row tm-translation-row">
  <div><div class="tm-path">{{label}}</div><div class="tm-source">{{source_value}}</div></div>
  <div class="admin-form-fields tm-form-editor">{{editor}}</div>
</div>
<!-- /kami:template -->
