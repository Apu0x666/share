<template>
    <g-layout>
        <v-card class="ml-14">
            <v-card-title>
                <inertia-link
                    :href="backLink" preserve-scroll preserve-state
                    as="v-btn" icon class="mr-2"
                ><v-icon>mdi-chevron-left</v-icon></inertia-link>
                <h3>{{title}}</h3>
                <v-spacer />
                <template>
                    <VueLoadingButton
                        aria-label="Отправить отчет"
                        :styled=true
                        class="mr-5 v-btn v-btn--is-elevated v-btn--has-bg theme--light v-size--default blue"
                        @click.native="sendReport"
                        :loading="sendReport_loading"
                        :disabled="checkDisabled"
                    >
                        Отправить отчет
                    </VueLoadingButton>
                </template>
                <template>
                    <VueLoadingButton
                        aria-label="Обновить ПО"
                        :styled=true
                        class="mr-5 v-btn v-btn--is-elevated v-btn--has-bg theme--light v-size--default blue"
                        @click.native="updateSoft"
                        :loading="updateSoft_loading"
                        :disabled="checkDisabled"
                    >
                        Обновить ПО
                    </VueLoadingButton>
                </template>
                
                <v-btn color="red" class="white--text" @click="deleteItem">Удалить</v-btn>
            </v-card-title>
            <v-card-text>
                <dictionary-loader
                    :items="[
                                'priority',
                                'server',
                                'service_type',
                                'source',
                                'status',
                                'system',
                                'user'
                                ]"
                >
                    <v-skeleton-loader v-if="dictionaryLoading" type="table"/>
                    <v-form v-else @submit.prevent="submit">
                        <v-form-base :model="formModel" :schema="formSchema" @input="formChange"/>
                        <v-btn type="submit" color="primary" :disabled="submitDisabled">Сохранить</v-btn>
                    </v-form>
                </dictionary-loader>
            </v-card-text>
        </v-card>
    </g-layout>
</template>

<script>
import {Inertia} from "@inertiajs/inertia";
import {mapState} from "vuex";
import {SweetAlert} from "../helpers/SweetAlert";
import axios
    from "axios";
import VueLoadingButton from 'vue-loading-button'

export default {
    name: "TicketEditor",
    props: {
        item: {},
        data: {},
    },
    data: (instance) => ({
        itemEdited: {},
        requestStarted: false,
        softwareDict: instance.data.software,
        applicantDict: instance.data.applicant,
        sendReport_loading: false,
        updateSoft_loading: false,
    }),
    components: {
        VueLoadingButton,
    },
    computed: {
        ...mapState('dictionary', ['dictionary', 'dictionaryLoading']),
        checkDisabled() {
            return this.item.status_id === "WON";
        },
        backLink(){
           return '/'+(this.isNew?'':`#ticket-${this.item.id}`)
        },
        submitDisabled(){
            if (this.checkDisabled) {
                return true;
            } else {
                return this.requestStarted
            }
        },
        newDefaults() {
            return this.dictionaryLoading?{}:
             this.itemEdited = {
                priority_id: "919",
                beginDateTime: new Date(),
                closeDateTime: new Date(),
                status_id: "EXECUTING",
                source_id: "RC_GENERATOR",
                system_id: this.dictionary?.system?.length > 0 ? this.dictionary.system[0].value : null
            }
        },
        formModel(){
            if (typeof this.itemEdited.software  !== 'object' && typeof this.itemEdited.software  !== 'undefined') {
                this.itemEdited = this.item;
                let currSoft = '';
                if (this.itemEdited.software.indexOf(',') > -1) {
                    currSoft = this.itemEdited.software.split(',').map(i => i.trim());
                } else {
                    currSoft = [this.itemEdited.software];
                }
                let newSoft = this.softwareDict.map(function (dictItem) {
                    if (currSoft.find(v => v === dictItem.text)) {
                        currSoft[currSoft.indexOf(dictItem.text)] = undefined;
                        return {
                            'text': dictItem.text,
                            'type_id': dictItem.type_id,
                            'value': dictItem.value,
                            'version': dictItem.version
                        }
                    }
                });
                newSoft = newSoft.filter(function (el) {
                    return el != null;
                });
                currSoft = currSoft.filter(function (el) {
                    return el != null;
                });
    
                currSoft.map(function (el) {
                    newSoft.push({
                        'text': el,
                    })
                });
                this.itemEdited.software = newSoft;
            }
            return this.dictionaryLoading ? {} : {
                ...this.isNew ? this.newDefaults : this.itemEdited
            }
        },
        formSchema() {
            return this.dictionaryLoading?{}:{
                system_id: {
                    type:'autocomplete', label:'АС',
                    items: this.dictionary.system,
                    col: 12,
                    disabled: this.checkDisabled
                },
                title: { type:'text', label:'Заявка', col: 12, disabled: this.checkDisabled},
                content: { type:'tiptap', label:'Описание', col: 12, disabled: this.checkDisabled},
                beginDateTime: { type:'fortus-calendar', label:'Дата поступления запроса', col: 4, disabled: this.checkDisabled},
                closeDateTime: { type:'fortus-calendar', label:'Дата завершения запроса', col: 4, disabled: this.checkDisabled},
                priority_id: {
                    type:'autocomplete', label:'Приоритет',
                    items: this.dictionary.priority,
                    col: 4,
                    disabled: this.checkDisabled
                },
                software: {
                    type: 'combobox', label: 'ПО',
                    items: [{header: 'Выберите элемент из списка или создайте новый'}, ...this.newSoftwareDictionaryWithTitle()],
                    'search-input.sync': 'search',
                    'hide-selected': true,
                    multiple: true,
                    'persistent-hint': true,
                    //rules: [REQUIRED],
                    clearable: this.isNew,
                    col: 12,
                    'small-chips': true,
                    'deletable-chips': true,
                    disabled: this.checkDisabled
                },
                status_id: {
                    type:'autocomplete', label:'Стадия',
                    items: this.dictionary.status,
                    col: 4,
                    disabled: this.checkDisabled
                },
                source_id: {
                    type:'autocomplete', label:'Источник',
                    items: this.dictionary.source,
                    clearable: true,
                    col: 4,
                    disabled: this.checkDisabled
                },
                applicant_id: {
                    type:'autocomplete', label:'Заявитель',
                    items: this.applicantDict,
                    clearable: true,
                    col: 4,
                    disabled: this.checkDisabled
                },
                report: { type:'textarea', label:'Отчёт', col: 12, outlined: true, disabled: this.checkDisabled},
                comment: { type:'textarea', label:'Пользовательский комментарий', col: 12, outlined: true, disabled: this.checkDisabled},
            }
        },
        isNew(){
            return !this.item?.id
        },
        title(){
            return this.isNew?'Создание заявки':`Редактирование заявки "${this.item.title}"`
        },
        editUrl() {
            return '/tickets/' + this.item.id
        }
    },
    mounted() {
        this.initialize()
        if(this.isNew){
            this.itemEdited = {
                ...this.newDefaults
            }
            this.$set(this.itemEdited, 'priority_id', "919")
        }
    },
    watch: {
        data(){
            this.initialize()
        },
    },
    methods: {
        initialize () {
            this.itemEdited = this.item;
        },
        newSoftwareDictionaryWithTitle() {
            return this.softwareDict.map(function (item) {
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
        },
        submit(){
            this.requestStarted = true
            if(this.itemEdited.beginDateTime){
                this.itemEdited.beginDateTime = this.itemEdited.beginDateTime.toLocaleString()
            }
            if(this.itemEdited.closeDateTime){
                this.itemEdited.closeDateTime = this.itemEdited.closeDateTime.toLocaleString()
            }
    
            let temp = this.itemEdited.software;
            this.$set(this.itemEdited, 'software', this.itemEdited.software_text)
            
            if(this.isNew){
                Inertia.post('/tickets', this.itemEdited)
            } else {
                Inertia.put('/tickets/' + this.item.id, this.itemEdited)
            }
    
            this.$set(this.itemEdited, 'software', temp)
        },
        async deleteItem(){
            let confirm = await SweetAlert.confirm('Вы уверены?')
            if(confirm){
                Inertia.delete(this.editUrl)
            }
        },
        async updateSoft(){
            this.updateSoft_loading = true;
            let id =  this.itemEdited.id;
            let json = true;
            await axios.post('/updateSoft',{id, json}).then((response) => {
                if (response.data.success !== undefined) {
                    SweetAlert.toast(response.data.success);
                    this.itemEdited.software = response.data.software;
                }
                if (response.data.error !== undefined) {
                    SweetAlert.toast(response.data.error, 'error');
                }
                this.updateSoft_loading = false;
            });
        },
        async setWonStatusForTicket() {
            let id = this.itemEdited.id;
            await axios.post('/setWonStatusForTicket', {id}).then((response) => {
                if (response.data.success !== undefined) {
                    SweetAlert.toast(response.data.success);
                    this.item.status_id = "WON";
                }
                if (response.data.error !== undefined) {
                    SweetAlert.toast(response.data.error, 'error');
                }
                this.updateSoft_loading = false;
            });
        },
        async sendReport(){
            this.sendReport_loading = true;
            let id =  this.itemEdited.id;
            await axios.post('/send-email',{id}).then((response) => {
                if (response.data.success !== undefined) {
                    SweetAlert.toast(response.data.success);
                    this.setWonStatusForTicket();
                }
                if (response.data.error !== undefined) {
                    SweetAlert.toast(response.data.error, 'error');
                }
                this.sendReport_loading = false;
            });
        },
    }

}
</script>

<style scoped>

</style>
