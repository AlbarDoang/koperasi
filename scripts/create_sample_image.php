<?php
// Create a tiny sample JPEG file for testing
$path = __DIR__ . DIRECTORY_SEPARATOR . 'example.jpg';
// Write a small valid PNG (1x1 transparent) and a small valid JPEG
$png_path = __DIR__ . DIRECTORY_SEPARATOR . 'example.png';
$jpg_path = __DIR__ . DIRECTORY_SEPARATOR . 'example.jpg';
$png_b64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/w8AAgEB/2G3mpkAAAAASUVORK5CYII=';
$jpg_b64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxISEhUQEhIVEBUVFRUVFRUVFRUVFRUVFRUWFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy0mICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAMAMBIgACEQEDEQH/xAAbAAACAgMBAAAAAAAAAAAAAAAFBgMEAAIBC//EADgQAAIBAgMGBgYDAAAAAAAAAAECAwQRAAUSITFBUQYTImFxgQYykaHB8BTxQlJDUlOCksLx/8QAGgEBAQEBAQEAAAAAAAAAAAAAAAECAwQf/EAC0RAAICAQMDBAIDAQAAAAAAAAABAhEDBBIhMRNBUWFxgZGh8BRCscHR/9oADAMBAAIRAxEAPwD7IAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//Z';
// write PNG
if (file_put_contents($png_path, base64_decode($png_b64)) === false) {
    echo "Failed to write PNG\n";
    exit(1);
}
// For JPG, prefer copying an existing valid image in repo when available
$source_jpg = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'gas_web' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . '1000786245.jpg';
if (file_exists($source_jpg)) {
    if (!copy($source_jpg, $jpg_path)) {
        echo "Failed to copy JPG from assets\n";
        exit(1);
    }
} else {
    // fallback to base64 write
    if (file_put_contents($jpg_path, base64_decode($jpg_b64)) === false) {
        echo "Failed to write JPG\n";
        exit(1);
    }
}

echo "Created sample images: $png_path, $jpg_path\n";
exit(0);
?>