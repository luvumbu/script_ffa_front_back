$(function() {
    var reader = {};
    var file = {};
    var slice_size = 1000 * 1024; // Taille de chaque segment
    function start_upload(event) {
        event.preventDefault();

        reader = new FileReader();
        file = document.querySelector('#file-input').files[0];

        upload_file(0);
    }

    $('#submit-button').on('click', start_upload);

    function upload_file(start) {
        var next_slice = start + slice_size + 1;
        var blob = file.slice(start, next_slice); // on ne voudra lire qu'un segment du fichier

        reader.onloadend = function (event) { // fonction à exécuter lorsque le segment a fini d'être lu
            if (event.target.readyState !== FileReader.DONE) {
                return;
            }

            $.ajax({
                url: "add_img/upload.php",
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: {
                    file_data: event.target.result,
                    file: file.name
                   
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log(jqXHR, textStatus, errorThrown);
                },
                success: function(data) {
                    var size_done = start + slice_size;
                    var percent_done = Math.floor((size_done / file.size) * 100);

                    if (next_slice < file.size) {
                         
                        $('#upload-progress').html('Uploading File - ' + percent_done + '%');

                        upload_file(next_slice); // s'il reste à lire, on appelle récursivement la fonction
                    } else {
                        $('#upload-progress').html('Upload Complete!');
                        
                       

                        setTimeout(function(){ 
                     document.location.reload(true);
                        }, 800);
                        


                    }
                }
            });
        };

        reader.readAsDataURL(blob); // lecture du segment
    }
});











