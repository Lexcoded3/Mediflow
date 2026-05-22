 /*
Template Name: StarCode & Dashboard Template
Author: MediFlow
Website: https://MediFlow.in/
Contact: MediFlow@gmail.com
File: plugins lightbox init js
*/

//basic example
const lightbox = GLightbox({
    touchNavigation: true,
    loop: true,
    autoplayVideos: true,
});

//description
const lightboxDescription = GLightbox({
    touchNavigation: true,
    loop: true,
    autoplayVideos: true,
    selector: '.description'
});

//video
var lightboxVideo = GLightbox({
    selector: '.video'
});