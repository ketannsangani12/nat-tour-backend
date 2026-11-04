<!DOCTYPE html>
<html>
<head>
<title>Welcome Home</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</head>
<body>
<div class="container">  
    <div class="jumbotron">
    </div>   

<form class="form-horizontal" action="{{route('import_new_save')}}" method="post" name="uploadCSV"
    enctype="multipart/form-data">
    <div class="input-row">
        <label class="col-md-4 control-label">Choose CSV File</label> <input
            type="file" name="file[]" id="file" accept=".csv" multiple>
        <button type="submit" id="submit" name="import"
            class="btn-submit">Import</button>
        <br />

    </div>
</form>
<script type="text/javascript">
    $(document).ready(
    function() {
        $("#frmCSVImport").on(
        "submit",
        function() {

            $("#response").attr("class", "");
            $("#response").html("");
            var fileType = ".csv";
            var regex = new RegExp("([a-zA-Z0-9\s_\\.\-:])+("
                    + fileType + ")$");
            if (!regex.test($("#file").val().toLowerCase())) {
                $("#response").addClass("error");
                $("#response").addClass("display-block");
                $("#response").html(
                        "Invalid File. Upload : <b>" + fileType
                                + "</b> Files.");
                return false;
            }
            return true;
        });
    });
</script>
</body> 
</html>