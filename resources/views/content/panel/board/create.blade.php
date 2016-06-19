@extends($user->isAnonymous() ? 'layouts.main.simplebox' : 'layouts.main.panel')

@section('title', $user->isAnonymous() ? trans("panel.title.board_create") : "")

@section('body')
{!! Form::open([
	'url'    => Request::url(),
	'method' => "PUT",
	'files'  => true,
	'id'     => "board-create",
	'class'  => "form-config",
]) !!}
	
	@if ($user->isAnonymous())
		<h3 class="config-title">@lang("panel.title.board_create_your")</h3>
	@else
		<h3 class="config-title">@lang("panel.title.board_create")</h3>
	@endif
	
	<fieldset class="form-fields" id="fields-board_basic">
		<legend class="form-legend">@lang('config.legend.board_basic')</legend>
		
		<!-- Board URI -->
		<div class="field row-board_uri">
				{!! Form::label(
					"board_uri",
					trans("config.option.board_uri"),
					[
						'class' => "field-label",
				]) !!}
				
				<span class="field-description">@lang("config.option.desc.board_uri")</span>
				
				{!! Form::text(
					"board_uri",
					old('username'),
					[
						'id'         => "board_uri",
						'class'      => "field-control",
						'max-length' => 31,
				]) !!}
		</div>
		
		<!-- Board title -->
		<div class="field row-board_uri">
				{!! Form::label(
					"title",
					trans("config.option.title"),
					[
						'class' => "field-label",
				]) !!}
				
				{!! Form::text(
					"title",
					old('username'),
					[
						'id'         => "title",
						'class'      => "field-control",
						'max-length' => 255,
				]) !!}
		</div>
		
		<!-- Board title -->
		<div class="field row-description">
				{!! Form::label(
					"description",
					trans("config.option.description"),
					[
						'class' => "field-label",
				]) !!}
				
				{!! Form::text(
					"description",
					old('description'),
					[
						'id'         => "description",
						'class'      => "field-control",
						'max-length' => 255,
				]) !!}
		</div>
		
		@if (!$user->isAnonymous())
		<div class="field row-captcha">
			<label class="field-label" for="captcha" data-widget="captcha">
				{!! captcha() !!}
			</label>
			<input class="field-control" id="captcha" name="captcha" type="text" />
		</div>
		@endif
	</fieldset>
	
	@if ($user->isAnonymous())
		@include($c->template('panel.auth.register.form'))
	@endif
	
	<div class="field">
		{!! Form::button(
			trans("config.create"),
			[
				'type'      => "submit",
				'class'     => "field-submit",
		]) !!}
	</div>
{!! Form::close() !!}
@endsection
