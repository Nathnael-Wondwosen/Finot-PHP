<?php
if (!isset($_COOKIE['seen_welcome']) && empty($_GET['skip_welcome'])) {
    header('Location: welcome.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>የፍኖተ ሰላም ሰ/ት/ቤት - ምዝገባ ተጠናቅቋል</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Ethiopic', sans-serif;
            direction: ltr;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .content-container {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.33); }
            80%, 100% { opacity: 0; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Error Toast Notification -->
    <?php if (isset($_GET['error']) && $_GET['error']): ?>
        <div id="error-toast" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 bg-red-100 border border-red-400 text-red-800 px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 animate-fade-in" style="min-width:300px;max-width:90vw;">
            <svg class="w-6 h-6 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="flex-1 text-base font-semibold">
                <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <button onclick="document.getElementById('error-toast').remove()" class="ml-4 text-red-600 hover:text-red-900 focus:outline-none">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .animate-fade-in { animation: fade-in 0.5s cubic-bezier(.4,0,.2,1); }
        </style>
        <script>
        setTimeout(function(){
            var toast = document.getElementById('error-toast');
            if (toast) toast.remove();
        }, 7000);
        </script>
    <?php endif; ?>

    <div class="container mx-auto px-4 py-6 max-w-3xl min-h-screen flex items-center">
        <div class="w-full">
            <!-- Header with Logo -->
            <div class="flex flex-col items-center mb-6">
                <div class="relative floating-animation">
                    <div class="absolute inset-0 w-20 h-20 md:w-24 md:h-24 rounded-full bg-blue-400 pulse-ring"></div>
                    <a href="welcome.php">
                        <img src="uploads/689636ec11381_finot logo.png" alt="Finot Logo" class="relative w-20 h-20 md:w-24 md:h-24 rounded-full shadow-lg object-contain border-3 border-white bg-white transition-all duration-300 hover:scale-105">
                    </a>
                </div>
                <h1 class="text-xl md:text-2xl font-bold text-center text-white mt-3 mb-1">የፍኖተ ሰላም ሰንበት ት/ቤት</h1>
                <div class="w-24 h-0.5 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full"></div>
            </div>
            
            <!-- Registration Closed Message -->
            <div class="content-container rounded-xl shadow-xl p-6 md:p-8 text-center">
                <div class="mb-6">
                    <div class="w-16 h-16 md:w-18 md:h-18 mx-auto mb-4 bg-gradient-to-r from-red-500 to-pink-500 rounded-full flex items-center justify-center shadow-lg">
                        <svg class="w-8 h-8 md:w-9 md:h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl md:text-2xl font-bold gradient-text mb-3">ምዝገባው ተጠናቅቋል</h2>
                    <div class="w-20 h-0.5 bg-gradient-to-r from-blue-500 to-purple-600 mx-auto mb-4 rounded-full"></div>
                </div>
                
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p class="text-base md:text-lg font-semibold text-blue-800">
                        ውድ ወላጆች እና ተማሪዎች፣
                    </p>
                    
                    <p class="text-sm md:text-base">
                        ለ<strong class="text-blue-700">2018 ዓ.ም የፍኖተ ሰላም ሰንበት ት/ቤት</strong> የተማሪ ምዝገባ ጊዜ ተጠናቅቋል።
                    </p>
                    
                    <p class="text-sm md:text-base">
                        የተመዘገባችሁት ሁሉ <span class="text-green-600 font-semibold">ከልብ እናመሰናልን!</span> 
                        ለአዲሱ የጥናት አመት ዝግጅታችንን እየሰራን ነው።
                    </p>
                    
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 my-5 text-left rounded-r-lg">
                        <h3 class="font-bold text-blue-800 mb-2 text-base flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            ቀጣይ  ስራዎችስራዎች                 </h3>
                        <ul class="space-y-2 text-gray-700">
                            <li class="flex items-start">
                                <span class="text-blue-500 mr-2 mt-0.5 text-sm">📋</span>
                                <span class="text-sm">የተመዘገባችሁ ተማሪዎች የክፍል ምደባ በቅርቡ እናስውቃልን</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-blue-500 mr-2 mt-0.5 text-sm">📞</span>
                                <span class="text-sm">ተጨማሪ መረጃ ለሚፈልጉ፣ እባክዎ በሰንበት ት/ቤቱ ስልክ ቁጥር ይደውሉ።</span>
                            </li>
                        </ul>
                    </div>
                    
                    <p class="text-sm md:text-base">
                        <strong class="text-gray-800">ላልተመዘገባችሁ፣</strong> ለበጠ መረጃ። 
                        እባክዎ የት/ቤቱን ማህበራዊ ሚዲያ ይከታተሉ።
                    </p>
                    
                    <div class="bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 rounded-lg p-4 my-5">
                        <p class="text-base font-semibold text-green-800 mb-2 flex items-center justify-center">
                            <span class="text-lg mr-2">🙏</span>
                            ምስጋና
                        </p>
                        <p class="text-gray-700 text-sm">
                            እግዚአብሄር ፍቅሩን በረከቱን ያብዛልን!
                        </p>
                    </div>
                    
                </div>
            </div>
            
            <!-- Additional Info Section -->
            <div class="mt-6 text-center">
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 text-white">
                    <h3 class="text-base mb-2">የቦሌ ሰሚት መካነ ሰላም መድኃኔዓለም እና መጥምቀ መለኮት ቅዱስ ዮሃንስ ቤ/ክ ፍኖተ ሰላም ሰንበት ት/ቤት</h3>
                    <p class="text-xs opacity-75">
                        © 2024 የፍኖተ ሰላም ሰንበት ት/ቤት። ሁሉም መብቶች የተጠበቁ ናቸው።
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Decorative elements -->
    <div class="fixed top-0 left-0 w-full h-full pointer-events-none overflow-hidden">
        <div class="absolute top-10 left-10 w-20 h-20 bg-white/10 rounded-full animate-pulse"></div>
        <div class="absolute top-1/4 right-10 w-16 h-16 bg-yellow-300/20 rounded-full animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute bottom-20 left-1/4 w-12 h-12 bg-blue-300/20 rounded-full animate-pulse" style="animation-delay: 2s;"></div>
        <div class="absolute bottom-10 right-1/3 w-8 h-8 bg-purple-300/20 rounded-full animate-pulse" style="animation-delay: 3s;"></div>
    </div>

    <script>
        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
        
        // Add entrance animation to main content
        document.addEventListener('DOMContentLoaded', function() {
            const contentContainer = document.querySelector('.content-container');
            contentContainer.style.opacity = '0';
            contentContainer.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                contentContainer.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                contentContainer.style.opacity = '1';
                contentContainer.style.transform = 'translateY(0)';
            }, 200);
        });
        
        // Add click ripple effect to buttons
        document.querySelectorAll('a[class*="bg-"]').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255,255,255,0.4);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
        
        // Define ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>