<div id="ModalVerEstado" class="modal">
        <div class="modal-content-verpedido">
          <div class='box box-primary'>
            <div class='box-header with-border'>
              <h4 class="box-title">Ver Producto: <span class="title-pedido"></span>
              <button class='btn btn-secondary btn-sm d-none' style='font-size:12px' id='btn-modificar-cliente'><i class='fa fa-edit'></i>  Modificar Cliente</button>
                  
            </h4>
              <button style="float:right;font-size: 1.6em" class="btn fa fa-close"
                onClick="CerrarModalEstado()"></button>
            </div>
            <div id="tablita">
              <div class='box-body'>

                <div class="row">
                  <div class="col-md-6">
                    <h5><span class="label-etapa"></span></h5>
                    <h5>Nombre: <span class="label-producto"></span></h5>
                    <h5>Cantidad: <span class="label-cantidad"></span> 
               </h5>
                    <h5>Ingresó el Día: <span class="label-fecha-ingreso"></span></h5>
                    <h5>Pasó a ETAPA 1: <span class="label-etapa1"></span></h5>
                    <h5>Pasó a ETAPA 2: <span class="label-etapa2"></span></h5>
                    <h5>Pasó a ETAPA 3: <span class="label-etapa3"></span></h5>
                    <h5>Pasó a ETAPA 4: <span class="label-etapa4"></span></h5>
                    <h5>Pasó a ETAPA 5: <span class="label-etapa5"></span></h5>
                    <h5>Se entregó el Día: <span class="label-fecha-entrega"></span></h5>
                    
                    
                    <div class="d-flex flex-row align-items-center mt-5">
                      <h5 class="text-primary">ETAPA 0: </h5>
                      <button id="btn-nofoto1" class="btn btn-sm btn-secondary ml-2 mb-1 btn-nofoto" disabled><i
                          class="fa fa-picture-o"></i> Sin Foto</button>

                      <button id="btn-verfoto1" class="btn btn-sm btn-info ml-2 mb-1 d-none btn-verfoto"><i
                          class="fa fa-picture-o"></i> Ver Foto</button>
                    </div>

                    <div class="d-flex flex-row align-items-center">
                      <h5 class="text-primary">ETAPA 1: </h5>
                      <button id="btn-nofoto2" class="btn btn-sm btn-secondary ml-2 mb-1 btn-nofoto" disabled><i
                          class="fa fa-picture-o"></i> Sin Foto</button>

                      <button id="btn-verfoto2" class="btn btn-sm btn-info ml-2 mb-1 d-none btn-verfoto"><i
                          class="fa fa-picture-o"></i> Ver Foto</button>
                    </div>

                    <div class="d-flex flex-row align-items-center">
                      <h5 class="text-primary">ETAPA 2: </h5>

                      <button id="btn-nofoto3" class="btn btn-sm btn-secondary ml-2 mb-1 btn-nofoto" disabled><i
                          class="fa fa-picture-o"></i> Sin Foto</button>

                      <button id="btn-verfoto3" class="btn btn-sm btn-info ml-2 mb-1 d-none btn-verfoto"><i
                          class="fa fa-picture-o"></i> Ver Foto</button>
                    </div>

                    <div class="d-flex flex-row align-items-center">
                      <h5 class="text-primary">ETAPA 3: </h5>

                      <button id="btn-nofoto4" class="btn btn-sm btn-secondary ml-2 mb-1 btn-nofoto" disabled><i
                          class="fa fa-picture-o"></i> Sin Foto</button>
                      <button id="btn-verfoto4" class="btn btn-sm btn-info ml-2 mb-1 d-none btn-verfoto"><i
                          class="fa fa-picture-o"></i> Ver Foto</button>
                    </div>

                    <div class="d-flex flex-row align-items-center">
                      <h5 class="text-primary">ETAPA 4: </h5>
                      <button id="btn-nofoto5" class="btn btn-sm btn-secondary ml-2 mb-1 btn-nofoto" disabled><i
                          class="fa fa-picture-o"></i> Sin Foto</button>
                      <button id="btn-verfoto5" class="btn btn-sm btn-info ml-2 mb-1 d-none btn-verfoto"><i
                          class="fa fa-picture-o"></i> Ver Foto</button>
                    </div>

                    <div class="d-flex flex-row align-items-center">
                      <h5 class="text-primary">ETAPA 5: </h5>
                      <button id="btn-nofoto6" class="btn btn-sm btn-secondary ml-2 mb-1 btn-nofoto" disabled><i
                          class="fa fa-picture-o"></i> Sin Foto</button>
                      <button id="btn-verfoto6" class="btn btn-sm btn-info ml-2 mb-1 d-none btn-verfoto"><i
                          class="fa fa-picture-o"></i> Ver Foto</button>
                    </div>

                    
                  </div>
                  <div class="col-md-6">
                    <div class="row">
                      <div class="col">
                        <div style='background-color:#e6e6e6;padding:5px'>
                          <span style='color:#74DF00;font-weight:bold;font-size:1.5em'>OBSERVACIONES:</span><br>
                          <textarea name='textarea' maxlength="100" class='form-control' readonly='true'
                            id='input-observaciones' type='text' rows="4"
                            style='width:100%;text-transform:uppercase;resize:none'>
                          </textarea>
                                                    
                        </div>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col">
                        <div style='background-color:#e6e6e6;padding:5px'>
                          <span class="text-danger" style='font-weight:bold;font-size:1.5em'>PROBLEMAS:</span><br>
                          <textarea name='textarea' maxlength="50" class='form-control' readonly='true'
                            id='input-problema' type='text' rows="4"
                            style='width:100%;text-transform:uppercase;resize:none'>
                          </textarea>
                          
                        </div>
                      </div>
                    </div>
                    
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div> <!-- MODAL FIN -->




    
  <div id="ModalControl" class="modal">
    <div class="modal-control">
      <div class='box box-primary'>
        <div class='box-header with-border'>
          <h3 class='box-title'>Control Etapa <span class="title-etapa"></span></h3>
          <button style="float:right;font-size: 1.6em" class="btn fa fa-close"
                onClick="$('#ModalControl').modal('hide')"></button>
        </div>
        <div class='box-body'>
          <div class='form-group'>
            <div class="row">
              <div class="col">
                <table class="table table-responsive w-100 d-block d-md-table tabla-control">
                  <thead class="thead-dark text-center">
                    
                  </thead>
                  <tbody>
                    
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

       
      </div>
    </div>
  </div>