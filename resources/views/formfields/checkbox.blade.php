<br>
<?php $checked = false; ?>
@if(isset($dataTypeContent->{$row->field}) || old($row->field))
    <?php $checked = old($row->field, $dataTypeContent->{$row->field}); ?>
@else
    <?php $checked = isset($options->checked) &&
        filter_var($options->checked, FILTER_VALIDATE_BOOLEAN) ? true: false; ?>
@endif

<?php $class = $options->class ?? "form-check-input"; ?>

<div class="form-check form-switch">
    <input type="checkbox" name="{{ $row->field }}" class="{{ $class }}" role="switch"
        @if(isset($options->on)) data-on="{{ $options->on }}" @endif
        @if(isset($options->off)) data-off="{{ $options->off }}" @endif
        @if($checked) checked @endif>
</div>
