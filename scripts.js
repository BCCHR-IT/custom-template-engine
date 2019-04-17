// Replace the <textarea id="editor1"> with a CKEditor
// instance, using default configuration.
function initializeEditor(id, height, pluginsFolder, pid)
{
    //CKEDITOR.plugins.addExternal( "codemirror", pluginsFolder + "codemirror/", "plugin.js" );
    CKEDITOR.replace( id, {
        extraPlugins: "uploadimage",
        imageUploadUrl: "fileManager/upload.php?type=Images",
        toolbar: [
            { name: "clipboard", items: [ "Undo", "Redo" ] },
            { name: "basicstyles", items: [ "Bold", "Italic", "Underline", "Strike", "RemoveFormat"] },
            { name: "paragraph", items: [ "NumberedList", "BulletedList", "-", "Outdent", "Indent", "-"] },
            { name: "insert", items: [ "Image", "Table", "HorizontalRule" ] },
            { name: "styles", items: [ "Format", "Font", "FontSize" ] },
            { name: "colors", items: [ "TextColor", "BGColor", "CopyFormatting" ] },
            { name: "align", items: [ "JustifyLeft", "JustifyCenter", "JustifyRight", "JustifyBlock" ] },
            { name: "source", items: ["Source", "searchCode", "Find", "SelectAll"] },
        ],
        height: height,
        bodyClass: "document-editor",
        contentsCss: [ "https://cdn.ckeditor.com/4.8.0/full-all/contents.css", "app.css" ],
        filebrowserBrowseUrl: "fileManager/browse.php?type=Image&pid=" + pid,
        filebrowserUploadUrl: "fileManager/upload.php?type=Images&pid=" + pid,
        filebrowserUploadMethod: "form",
        fillEmptyBlocks: false,
        extraAllowedContent: "*{*}",
        font_names: "Arial/Arial, Helvetica, sans-serif; Times New Roman/Times New Roman, Times, serif; Courier; DejaVu"
    });
}

$(document).ready(function () {
    $(this).find(".fa-caret-up").hide();
    $(this).find(".fa-caret-down").show();
});

$(".collapsible").click(function() {
    $(this).find(".fa-caret-down").toggle();
    $(this).find(".fa-caret-up").toggle();
    $(this).next().toggle();
});

$("#readmore-link").click(function () {
    $("#readmore").toggle();
});