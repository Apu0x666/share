<template>
    <v-dialog v-model="show">
        <template v-slot:activator="{ on, attrs }">
            <v-btn
                v-bind="$attrs"
                v-on="on"
            ><slot /></v-btn>
        </template>
        <v-form @submit.prevent="submit" v-model="formValid" ref="form" v-if="show">
            <v-card>
                <v-card-title :data-item="JSON.stringify(formModel)">
                    {{title}} по заявке "{{ticketTitle}}"
                </v-card-title>
                <v-card-text>
                    <dictionary-loader :items="['service_type', 'software', 'user']">
                        <v-skeleton-loader type="article" v-if="dictionaryLoading"/>
                        <v-form-base v-else :model="formModel" :schema="formSchema" @input="formChange" :data-schema="JSON.stringify(formSchema)"/>
                    </dictionary-loader>
                </v-card-text>
                <v-card-actions>
                    <v-btn v-if="!isNew" color="error" class="white--text" @click="deleteItem">Удалить</v-btn>
                    <v-spacer />
                    <v-btn @click="cancel">Отмена</v-btn>
                    <v-btn type="submit" color="primary" :disabled="disableSubmit">Сохранить</v-btn>
                </v-card-actions>
            </v-card>
        </v-form>
    </v-dialog>
</template>

<script>
import {
    mapState
} from "vuex";
import axios
    from "axios";
import {
    SweetAlert
} from "../../helpers/SweetAlert";

const REQUIRED = (v) => !!v || 'обязательно для заполнения'
let start = (new Date())
let finish = (new Date())
start.setHours(0, 0 ,0)
finish.setHours(0, 0 ,0)

export default {
    name: "TicketServicesModal",
    props: {
        ticketId: {},
        ticketTitle: {},
        item: {default: () => ({})}
    },
    data: () => ({
        show: false,
        requestActive: false,
        itemEdited: {},
        formValid: false,
        newDefaults: {
            start,
            finish,
        },
    }),
    computed:{
        ...mapState('dictionary', ['dictionary', 'dictionaryLoading']),
        formModel(){
            if(this.show) {
                return this.isNew ? {...this.item, ...this.newDefaults, user_id: this.$page?.props?.user?.sub} : {...this.item}
            }
            return null
        },
        isNew(){
            return !this.item?.id
        },
        disableSubmit(){
            return this.requestActive || !this.formValid || Object.keys(this.itemEdited).length < 1
        },
        title(){
            return (this.isNew?
                'Добавление услуги':
                'Редактирование услуги')
        },
        selectedServiceTypeGroupId(){
            return this.dictionary?.service_type?.find(v => v.value === this.itemEdited.service_type_id)?.groupId
        },
        selectedSoftwareTypeId(){
            return this.dictionary?.software?.find(v => v.value === this.itemEdited.software_id)?.type_id
        },
        formSchema(){
            return this.dictionaryLoading?{}:{
                // title: { type:'text', label:'Название', col: 12 },
                // content: { type:'textarea', label:'Описание', col: 12 },
                service_type_id: {
                    type:'autocomplete', label:'Услуга',
                    items: this.dictionary?.service_type?.filter(
                        v => !this.isNew ||  (v.actual &&
                            (!this.selectedSoftwareTypeId ||
                                this.selectedSoftwareTypeId === v.groupId
                            ))
                    ),
                    disabled: !this.isNew,
                    clearable: this.isNew,
                    // rules: [REQUIRED], validateOnBlur: false,
                    col: 12
                },
                software: {
                    type: 'combobox', label: 'ПО',
                    items: [{header: 'Выберите элемент из списка или создайте новый'}, ...this.newSoftwareDictionaryWithTitle()],
                    'search-input.sync': 'search',
                    'hide-selected': true,
                    multiple: true,
                    'persistent-hint': true,
                    rules: [REQUIRED],
                    clearable: this.isNew,
                    col: 12,
                    'small-chips': true,
                    'deletable-chips': true,
                },
                start: {
                    type:'fortus-calendar', label:'Дата начала', col: 6, autoApply: true, noDefault: true,
                    rules: [REQUIRED],
                },
                finish: { type:'fortus-calendar', label:'Дата завершения', col: 6, autoApply: true, noDefault: true,
                    rules: [REQUIRED],
                },
                content: { type:'textarea', label:'Описание', col: 12, outlined: true },
                user_id: {
                    type:'autocomplete', label:'Пользователь',
                    items: this.dictionary?.user,
                    value: this.$page.props.user?.sub,
                    col: 12, disabled: true
                },
            }
        }
    },
    mounted() {
        this.loadDefaults()
    },
    watch: {
        show(){
            this.loadDefaults()
        }
    },
    methods: {
        newSoftwareDictionaryWithTitle() {
            return this.dictionary?.software.map(function (item) {
                return {
                    'text': item.text + ((item.version) ? ' ' + item.version : ''),
                    'type_id': item.type_id,
                    'value': item.value,
                    'version': item.version
                }
            });
        },
        getArrElemForSoftwareText(text) {
            let res = this.newSoftwareDictionaryWithTitle().find(v => v.text === text);
            if (res) {
                return res;
            } else {
                return {
                    'text': text,
                }
            }
        },
        loadDefaults(){
            if(this.isNew){
                this.itemEdited = {
                    ...this.newDefaults
                }
            }
        },
        formChange(e){
            if (e.key === 'software') {
                let text_res = []
                let arr_res = []
                let app = this;
                e.value.forEach(function (value){
                    if (typeof value === 'string') {
                        text_res.push(value);
                        arr_res.push(app.getArrElemForSoftwareText(value));
                    } else {
                        text_res.push(value.text);
                        arr_res.push(app.getArrElemForSoftwareText(value.text));
                    }
                });
                this.$set(this.itemEdited, e.key, arr_res)
                this.$set(this.itemEdited, 'software_text', text_res.join(', '))
            } else {
                this.$set(this.itemEdited, e.key, e.value)
            }
            this.$refs.form.validate()
        },
        async submit(){
            if(!this.disableSubmit){
                this.requestActive = true
                let res
                if(this.itemEdited?.start) {
                    this.itemEdited.start = this.itemEdited?.start?.toDateString()
                }
                if(this.itemEdited.finish ) {
                    this.itemEdited.finish = this.itemEdited?.finish?.toDateString()
                }
                //свапаем значения для правильного сохранения, массив возвращается на экран обновления, текстовый аналог отправляется в сохранение
                let temp = this.itemEdited.software
                this.$set(this.itemEdited, 'software', this.itemEdited.software_text)

                if(this.isNew){
                    res = await  axios.post('/tickets/'+this.ticketId+'/services', this.itemEdited)
                } else {
                    res = await  axios.put('/tickets/'+this.ticketId+'/services/' + this.item.id, this.itemEdited)
                }
                if(res?.data?.success){
                    let changed = {}

                    this.$set(this.itemEdited, 'software', temp)

                    if(this.isNew) {
                        changed = res.data.item
                    } else {
                        changed = {...this.item, ...this.itemEdited}
                        changed.start = changed?.start?.toLocaleString?.() || changed?.start
                        changed.finish = changed?.finish?.toLocaleString?.() || changed?.finish
                    }
                    changed.synced = 0;
                    this.$emit('change', {changed, isNew: this.isNew})
                    this.itemEdited = {}
                    this.show = false
                } else {
                    SweetAlert.error('Ошибка', res?.data?.error ?? '')
                    console.error(res?.data?.trace);
                }
                this.requestActive = false
            }
        },
        cancel(){
            this.itemEdited = {}
            this.show = false
        },
        async deleteItem(){
            let confirm = await SweetAlert.confirm('Вы уверены?')
            if(confirm){
                let res = await  axios.delete('/tickets/'+this.ticketId+'/services/' + this.item.id);
                if(res.data.success){
                    this.show = false
                    this.$emit('change', {changed: {...this.item, deleted: true}})
                } else {
                    SweetAlert.error('Ошибка', res?.data?.error ?? '')
                    console.error(res?.data?.trace);
                }
            }
        }
    }
}
</script>

<style scoped>

</style>
