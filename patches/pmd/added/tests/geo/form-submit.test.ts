import {it,expect,vi} from 'vitest';
import {mount,flushPromises} from '@vue/test-utils';
import {defineComponent,ref} from 'vue';
import XForm from '@/components/x-form/XForm.vue';
import AddressFields from '@/components/form/AddressFields.vue';
import '@/validator';
vi.mock('@/store/general',()=>({useGeneralStore:()=>({entity:{ibgeCodes:[]},map:{center:{lat:-26.99,lng:-48.63}}})}));
vi.mock('@/components/form/InputFroala.vue',()=>({default:{template:'<textarea />'}}));
function mountForm(disabled=false) {
 (window as any).config={city:'Balneario Camboriu'};
 const Host=defineComponent({components:{XForm,AddressFields},setup(){return {address:ref({postalCode:'',address:'',number:'',complement:'',neighborhood:'',city:'',stateAbbreviation:'',cityIbgeCode:0,lat:0,lng:0,manualChangeLocation:false}),schema:{fields:[],buttons:[{type:'submit',label:'Prosseguir'}],buttonsContainer:{class:''}},receive:vi.fn(), disabled:ref(disabled)};},template:`<XForm :schema="schema" :disable-proceed="disabled" @submit="receive"><template #default="{errors,setFieldValue}"><AddressFields v-model:data="address" name="address" :errors="errors" :set-field-value="setFieldValue" :fetching-address-lat-lng="false"/></template></XForm>`});
 return mount(Host,{attachTo:document.body,global:{stubs:{GeoMap:{template:'<div><slot :map="{}" /></div>'},GeoMarker:true}}});
}
it('submits the declared address without coordinates or location confirmation',async()=>{
 const wrapper=mountForm();
 for (const [key,value] of Object.entries({postalCode:'88330-000',address:'Rua de teste',number:'10',neighborhood:'Centro',city:'Balneario Camboriu',stateAbbreviation:'SC'})) {
   const input=wrapper.findAll('input').find(input=>input.attributes('name')===`address.${key}`)!;
   await input.setValue(value);
 }

 expect(wrapper.findAll('input[type="number"]')).toHaveLength(0);
 const proceed=wrapper.findAll('button').find(button=>button.text().includes('Prosseguir'))!;
 expect(proceed.attributes('disabled')).toBeUndefined();
 await proceed.trigger('click');
 await flushPromises(); await new Promise(resolve=>setTimeout(resolve,30));
 expect(wrapper.vm.receive).toHaveBeenCalled();
 const payload=(wrapper.vm.receive as any).mock.calls[0][0];
 expect(wrapper.text()).not.toContain('Confirmar localização');
 expect(payload.address.address).toBe('Rua de teste');
 wrapper.unmount();
});

it('shows validation errors rather than silently refusing to proceed',async()=>{
 const wrapper=mountForm();
 await wrapper.findAll('button').find(button=>button.text().includes('Prosseguir'))!.trigger('click');
 await flushPromises(); await new Promise(resolve=>setTimeout(resolve,30));
 expect(wrapper.vm.receive).not.toHaveBeenCalled();
 expect(wrapper.findComponent(XForm).emitted('invalid-submit')).toBeTruthy();
 expect(wrapper.text()).toContain('Confira os campos abaixo');
 expect(wrapper.text()).toContain('CEP');
 wrapper.unmount();
});
it('disables only while pending and enables the actual proceed button afterwards',async()=>{
 const wrapper=mountForm(true);
 const proceed=wrapper.findAll('button').find(button=>button.text().includes('Prosseguir'))!;
 expect(proceed.attributes('disabled')).toBeDefined();
 (wrapper.vm as any).disabled=false;
 await wrapper.vm.$nextTick();
 expect(proceed.attributes('disabled')).toBeUndefined();
 await proceed.trigger('click');
 await flushPromises(); await new Promise(resolve=>setTimeout(resolve,30));
 expect(wrapper.findComponent(XForm).emitted('invalid-submit')).toBeTruthy();
 wrapper.unmount();
});
