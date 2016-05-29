<nav class="cp-side">
    <section class="cp-linklists">
        <ul class="cp-linkgroups">
            @if ($user->canAdminPermissions())
            <li class="cp-linkgroup">
                <a class="linkgroup-name">@lang('nav.panel.secondary.users.permissions')</a>

                <ul class="cp-linkitems">
                    <li class="cp-linkitem">
                        <a class="linkitem-name" href="{!! url('cp/roles/permissions') !!}">@lang('nav.panel.secondary.users.role_permissions')</a>
                    </li>
                </ul>
            </li>
            @endif
        </ul>
    </section>
</nav>
