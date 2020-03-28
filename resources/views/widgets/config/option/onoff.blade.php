<dl class="option option-{{{ $option_name }}}">
    <dt class="option-term"></dt>
    <dd class="option-definition">
        {!! Form::checkbox(
            $option_name,
            1,
            !!$option_value,
            [
                'id'        => $option_name,
                'class'     => "field-control",
                    user()->cannot('setting-edit', [$board ?? null, $option ?? null]) ? 'disabled' : 'data-enabled',
        ]) !!}
        {!! Form::label(
            $option_name,
            trans("config.option.{$option_name}"),
            [
                'class' => "field-label",
        ]) !!}

        @include('widgets.config.lock')

        @include('widgets.config.helper')
    </dd>
</dl>
