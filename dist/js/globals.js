const logoPrintImg = "dist/img/roel.jpg";

var globals = {
    logoPrintImg: logoPrintImg,
    printHeader: `
    <div class="row">
        <div class="col text-center">
            <img src='${logoPrintImg}' class="logo-print"></img>
            <address style='font-size:12px !important;padding-top:3px;padding-bottom:10px;'>
            Paradero 7 de San Pedro<br> 
            Quillota, Valparaíso<br>
            Tel.: +56 944 988 254<br>
            <p>E-mail: ventas@roelplant.cl</p>
            </address>
        </div>
    </div>
    `,
    printHeaderSimple: `
    <div align='center'>
        <img src='${logoPrintImg}' class="logo-print"></img>
        <address style='font-size:10px;'>
            <p>ventas@roelplant.cl</p>
        </address>
    </div><br><br>`,
}

