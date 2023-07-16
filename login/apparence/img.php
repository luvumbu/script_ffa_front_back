<input type="file" id="file">
<button onclick="uploadImage()">Envoyer l'image</button>
<div id="preview"></div>
<script>
    function uploadImage() {
  const input = document.getElementById("file");
  const file = input.files[0];

  const xhr = new XMLHttpRequest();
  const formData = new FormData();

  formData.append("image", file);

  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4 && xhr.status === 200) {
      const imageUrl = xhr.responseText;
      const preview = document.getElementById("preview");
      const img = document.createElement("img");
      img.src = imageUrl;
      preview.appendChild(img);
    }
  };

  xhr.open("POST", "upload.php", true);
  xhr.send(formData);
}

</script>